<?php

namespace App\Console\Commands;

use App\Mail\WeeklyOverview;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('patchmon:weekly-overview')]
#[Description('Email the oversight admins a weekly overview of the patching estate.')]
class SendWeeklyOverview extends Command
{
    public function handle(): int
    {
        $oversightAdmins = User::oversightAdmins()->get();

        if ($oversightAdmins->isEmpty()) {
            return self::SUCCESS;
        }

        Mail::to($oversightAdmins)->queue(new WeeklyOverview);

        return self::SUCCESS;
    }
}
