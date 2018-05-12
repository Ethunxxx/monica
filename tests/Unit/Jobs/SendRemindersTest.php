<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendReminderEmail;
use App\Jobs\SetNextReminderDate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SendRemindersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_schedules_a_reminder_email_job_and_a_set_next_expected_date_job()
    {
        Bus::fake();

        Carbon::setTestNow(Carbon::create(2017, 1, 1, 7, 0, 0));

        $account = factory('App\Account')->create([
            'default_time_reminder_is_sent'       => '7:00',
            'has_access_to_paid_version_for_free' => 1,
        ]);
        $contact = factory('App\Contact')->create(['account_id' => $account->id]);
        $user = factory('App\User')->create(['account_id' => $account->id]);
        $reminder = factory('App\Reminder')->create([
            'account_id'         => $account->id,
            'contact_id'         => $contact->id,
            'next_expected_date' => '2017-01-01',
        ]);

        $exitCode = Artisan::call('send:reminders', []);

        Bus::assertDispatched(SendReminderEmail::class);
        Bus::assertDispatched(SetNextReminderDate::class);
    }

    public function test_it_schedules_multiple_emails_jobs_but_only_one_set_next_reminder_job()
    {
        Bus::fake();

        Carbon::setTestNow(Carbon::create(2017, 1, 1, 7, 0, 0));

        $account = factory('App\Account')->create([
            'default_time_reminder_is_sent'       => '7:00',
            'has_access_to_paid_version_for_free' => 1,
        ]);
        $contact = factory('App\Contact')->create(['account_id' => $account->id]);
        $user1 = factory('App\User')->create(['account_id' => $account->id]);
        $user2 = factory('App\User')->create(['account_id' => $account->id]);
        $reminder = factory('App\Reminder')->create([
            'account_id'         => $account->id,
            'contact_id'         => $contact->id,
            'next_expected_date' => '2017-01-01',
        ]);

        $exitCode = Artisan::call('send:reminders', []);

        Bus::assertDispatched(SendReminderEmail::class, 2);
        Bus::assertDispatched(SetNextReminderDate::class, 1);
    }

    public function test_it_doesnt_schedule_email_if_on_unpaid_plan()
    {
        Bus::fake();

        config(['monica.requires_subscription' => true]);

        Carbon::setTestNow(Carbon::create(2017, 1, 1, 7, 0, 0));

        $account = factory('App\Account')->create([
            'default_time_reminder_is_sent'       => '7:00',
            'has_access_to_paid_version_for_free' => 0,
        ]);
        $contact = factory('App\Contact')->create(['account_id' => $account->id]);
        $user = factory('App\User')->create(['account_id' => $account->id]);
        $reminder = factory('App\Reminder')->create([
            'account_id'         => $account->id,
            'contact_id'         => $contact->id,
            'next_expected_date' => '2017-01-01',
        ]);

        $exitCode = Artisan::call('send:reminders', []);

        Bus::assertNotDispatched(SendReminderEmail::class);
        Bus::assertDispatched(SetNextReminderDate::class, 1);
    }
}
