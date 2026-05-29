<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Notification::latest();
        $query->when($this->role($request) !== 'admin', fn ($q) => $q->where('recipient_role', $this->role($request)));
        return $this->ok($query->paginate((int) $request->input('per_page', 15)));
    }

    public function show(Request $request, Notification $notification) { return $this->ok($notification); }

    public function update(Request $request, Notification $notification)
    {
        $notification->update(['read_at' => now()]);
        return $this->ok($notification, 'Notification marked read.');
    }

    public function destroy(Request $request, Notification $notification)
    {
        $notification->delete();
        return $this->ok([], 'Notification deleted.');
    }
}
