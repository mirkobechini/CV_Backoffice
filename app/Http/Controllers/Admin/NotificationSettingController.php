<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use Illuminate\Http\Request;

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

    public function update(Request $request)
    {
        foreach ($request->except('_token', '_method') as $key => $value) {
            NotificationSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        return redirect()->route('admin.notifications.edit')
            ->with('status', 'Impostazioni aggiornate con successo.');
    }
}
