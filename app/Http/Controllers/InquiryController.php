<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverInquiryEmail;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Support\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InquiryController extends Controller
{
    public function token(Request $request)
    {
        if (! $request->session()->has('contact_started')) {
            $request->session()->put('contact_started', time());
        }

        return response()->json(['token' => $request->session()->token()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email:rfc|max:254',
            'type' => ['required', Rule::in(Portfolio::INQUIRY_TYPES)],
            'subject' => 'required|string|max:160', 'message' => 'required|string|min:10|max:6000',
            'website' => 'nullable|string|size:0',
        ]);
        $started = $request->session()->get('contact_started');
        if (! $started || time() - $started < 2) {
            throw ValidationException::withMessages(['message' => 'Please wait a moment and submit the form again.']);
        }

        DB::transaction(function () use ($data) {
            $inquiry = Inquiry::create(Arr::only($data, ['name', 'email', 'type', 'subject']));
            $message = $inquiry->messages()->create(['direction' => 'incoming', 'body' => $data['message']]);
            $this->queue($message, 'notification');
        });

        return response()->json(['message' => 'Your inquiry was sent. Thank you for reaching out.'], 201);
    }

    public function index(Request $request)
    {
        $filter = $request->validate(['status' => ['nullable', Rule::in(Portfolio::INQUIRY_STATUSES)]])['status'] ?? null;
        $inquiries = Inquiry::when($filter, fn ($query) => $query->where('status', $filter))
            ->latest('id')->paginate(20)->onEachSide(1)->withQueryString();

        return Inertia::render('Inbox', ['inquiries' => $inquiries, 'filter' => $filter, 'statuses' => Portfolio::INQUIRY_STATUSES]);
    }

    public function show(Inquiry $inquiry)
    {
        return Inertia::render('Thread', [
            'inquiry' => $inquiry->load('messages.delivery'),
            'statuses' => Portfolio::INQUIRY_STATUSES,
        ]);
    }

    public function reply(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate(['message' => 'required|string|max:6000']);
        DB::transaction(function () use ($inquiry, $data) {
            $message = $inquiry->messages()->create(['direction' => 'outgoing', 'body' => $data['message']]);
            $this->queue($message, 'reply');
            AuditLog::record('Queued reply', 'Inquiry', $inquiry->id);
        });

        return back()->with('notice', 'Reply queued for delivery.');
    }

    public function status(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate(['status' => ['required', Rule::in(Portfolio::INQUIRY_STATUSES)]]);
        DB::transaction(function () use ($inquiry, $data) {
            $inquiry->update($data);
            AuditLog::record('Set status to '.$data['status'], 'Inquiry', $inquiry->id);
        });

        return back()->with('notice', 'Inquiry status saved.');
    }

    private function queue(InquiryMessage $message, string $kind): void
    {
        $delivery = $message->delivery()->create(['kind' => $kind]);
        Bus::dispatch((new DeliverInquiryEmail($delivery->id))->beforeCommit());
    }
}
