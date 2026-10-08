<?php
namespace App\Console\Commands;
use App\Models\NotificationLog;
use App\Models\Reservation;
use App\Notifications\ReservationVisitReminderNotification;
use Illuminate\Console\Command;
class RemindReservationVisits extends Command
{
 protected $signature = 'reservations:remind';
 protected $description = 'Send one reminder 24 hours before a scheduled reservation visit';
 public function handle(): int {
  $count=0;
  Reservation::with('user')->where('status','confirmed')->whereNotNull('preferred_visit_at')->whereNull('reminder_sent_at')->whereBetween('preferred_visit_at',[now()->addHours(23),now()->addHours(25)])->orderBy('id')->chunkById(100,function($items)use(&$count){foreach($items as $r){$r->user->notify(new ReservationVisitReminderNotification($r));NotificationLog::create(['user_id'=>$r->user_id,'type'=>'reservation_visit_reminder','recipient'=>$r->user->email,'subject'=>'Reminder: planned visit to Lance Furniture Shop','status'=>config('queue.default')==='sync'?'sent':'queued','sent_at'=>config('queue.default')==='sync'?now():null,'payload'=>['reservation'=>$r->reference,'branch'=>$r->branch,'preferred_visit_at'=>$r->preferred_visit_at->toIso8601String()]]);$r->update(['reminder_sent_at'=>now()]);$r->transactions()->create(['action'=>'visit_reminder_sent','notes'=>'Automatic 24 hour visit reminder queued.']);$count++;}});
  $this->info("Queued {$count} visit reminders."); return self::SUCCESS;
 }
}
