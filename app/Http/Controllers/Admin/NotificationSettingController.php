<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationSettingRequest;
use App\Models\NotificationSetting;

class NotificationSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $settings = NotificationSetting::all();
        return view('admin.notifications.index', compact('settings'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        $settings = NotificationSetting::pluck('value', 'key');
        return view('admin.notifications.edit', compact('settings'));
    }

    public function update(UpdateNotificationSettingRequest $request)
    {
        $data = $request->validated();

        foreach ($data as $key => $value) {
            if (in_array($key, ['notify_on_maintenance', 'notify_on_deadline', 'notify_on_issue', 'notify_on_equipment'], true)) {
                $value = $request->boolean($key);
            }

            NotificationSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        return redirect()->route('admin.notifications.edit')
            ->with('status', 'Impostazioni aggiornate con successo.');
    }
}
