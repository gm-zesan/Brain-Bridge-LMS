<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request, $userId)
    {
        $me = $request->user()->id;

        $messages = ChatMessage::where(function ($q) use ($me, $userId) {
            $q->where('sender_id', $me)->where('receiver_id', $userId);
        })->orWhere(function ($q) use ($me, $userId) {
            $q->where('sender_id', $userId)->where('receiver_id', $me);
        })->orderBy('created_at', 'asc')->get();

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'body' => 'required|string',
        ]);

        $message = ChatMessage::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $data['receiver_id'],
            'body' => $data['body'],
        ]);

        event(new MessageSent($message));

        return response()->json(['data' => $message], 201);
    }
}
