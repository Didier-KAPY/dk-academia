<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('public.contact');
    }

    public function store(ContactMessageRequest $request): RedirectResponse
    {
        ContactMessage::create($request->safe()->only(['name', 'email', 'phone', 'subject', 'message']));

        return redirect()->route('contact')->with(
            'status',
            'Votre message a bien été transmis. Notre équipe vous répondra dès que possible.',
        );
    }
}
