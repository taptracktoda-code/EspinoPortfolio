<?php

namespace App\Jobs;

use App\Models\EmailDeliveryLog;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class DeliverInquiryEmail implements ShouldQueue
{
    use Queueable;

    public $tries = 4;

    public $timeout = 45;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->deliveryId))->releaseAfter(60)->expireAfter(90)];
    }

    public function handle(): void
    {
        $delivery = EmailDeliveryLog::with('message.inquiry')->findOrFail($this->deliveryId);
        if ($delivery->status === 'Sent') {
            return;
        }

        $message = $delivery->message;
        $inquiry = $message->inquiry;
        $notification = $delivery->kind === 'notification';
        $delivery->increment('attempts');

        try {
            $to = $notification ? User::where('owner_slot', true)->value('email') : $inquiry->email;
            if (! $to) {
                throw new RuntimeException('No email recipient is configured.');
            }

            $subject = preg_replace('/[\r\n]+/', ' ', $inquiry->subject);
            $content = $notification
                ? "A new {$inquiry->type} was submitted by {$inquiry->name}.\n\nOpen the owner inbox to read and reply:\n".route('admin.inquiries.show', $inquiry)
                : $message->body;
            Mail::raw($content, function ($mail) use ($to, $notification, $subject) {
                $mail->to($to)->subject(($notification ? 'Portfolio inquiry: ' : 'Re: ').$subject);
            });
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'Retrying', 'error' => class_basename($exception)]);
            Log::warning('Portfolio email attempt failed.', ['delivery' => $delivery->id, 'exception_type' => get_class($exception)]);

            // Keep mail transport details and client addresses out of failed-job records.
            throw new RuntimeException('Portfolio email transport failed for delivery '.$delivery->id);
        }

        DB::transaction(function () use ($delivery, $notification, $inquiry) {
            $delivery->update(['status' => 'Sent', 'error' => null]);
            if (! $notification) {
                Inquiry::whereKey($inquiry->id)->whereIn('status', ['New', 'Read'])->update(['status' => 'Replied']);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        EmailDeliveryLog::whereKey($this->deliveryId)->where('status', '!=', 'Sent')
            ->update(['status' => 'Failed', 'error' => class_basename($exception ?? RuntimeException::class)]);
        Log::error('Portfolio email exhausted delivery attempts.', ['delivery' => $this->deliveryId]);
    }
}
