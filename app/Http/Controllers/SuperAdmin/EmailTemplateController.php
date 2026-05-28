<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::orderBy('type')->orderBy('name')->get();

        return Inertia::render('SuperAdmin/EmailTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function edit(EmailTemplate $emailTemplate)
    {
        return Inertia::render('SuperAdmin/EmailTemplates/Edit', [
            'template' => [
                'id' => $emailTemplate->id,
                'name' => $emailTemplate->name,
                'type' => $emailTemplate->type,
                'subject' => $emailTemplate->subject,
                'body' => $emailTemplate->body,
                'variables' => $emailTemplate->variables ?? [],
                'is_active' => $emailTemplate->is_active,
            ],
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $emailTemplate->update($validated);

        return redirect()->route('superadmin.email-templates.index')
            ->with('success', 'Email template updated successfully!');
    }
}
