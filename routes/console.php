<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

Artisan::command('portfolio:owner {email : Private login email} {--reset : Change the owner password} {--change-email : Change the owner login email}', function () {
    $email = strtolower(trim($this->argument('email')));
    $owner = User::where('owner_slot', true)->first();
    $reset = $this->option('reset');
    $changeEmail = $this->option('change-email');

    if ($reset && $changeEmail) {
        $this->error('Choose either --reset or --change-email.');

        return 1;
    }

    if (! $owner && ($reset || $changeEmail)) {
        $this->error('Create an owner account before updating its credentials.');

        return 1;
    }

    if ($changeEmail) {
        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:254', Rule::unique(User::class)->ignore($owner)],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return 1;
        }

        if ($owner->email !== $email) {
            DB::transaction(function () use ($owner, $email) {
                $owner->forceFill(['email' => $email, 'email_verified_at' => null, 'remember_token' => null])->save();
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table'))->where('user_id', $owner->id)->delete();
                }
            });
        }

        $this->info('Owner login email saved.');

        return 0;
    }

    if ($owner && (! $reset || $owner->email !== $email)) {
        $this->error('Use --reset with the current login email or --change-email with the new email.');

        return 1;
    }

    $password = $this->secret('Owner password');
    $confirmation = $this->secret('Confirm owner password');
    $validator = Validator::make(
        ['email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
        ['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'confirmed', 'max:72', Password::min(14)]],
    );

    if ($validator->fails() || strlen((string) $password) > 72) {
        $this->error($validator->errors()->first() ?: 'The password must fit within 72 bytes.');

        return 1;
    }

    if ($owner) {
        $owner->update(['password' => $password]);
        if (config('session.driver') === 'database') {
            DB::table(config('session.table'))->where('user_id', $owner->id)->delete();
        }
    } else {
        if (User::where('email', $email)->exists()) {
            $this->error('This login email already belongs to an existing user.');

            return 1;
        }

        (new User)->forceFill([
            'name' => 'Owner', 'email' => $email, 'password' => $password, 'owner_slot' => true,
        ])->save();
    }

    $this->info('Owner credentials saved.');

    return 0;
})->purpose('Provision the sole portfolio owner');
