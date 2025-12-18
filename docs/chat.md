Real-time Chat (Laravel + Pusher) — Setup & Frontend Example

Backend (Laravel API)
- Files added:
  - `app/Models/ChatMessage.php`
  - `database/migrations/2025_12_18_000000_create_chat_messages_table.php`
  - `app/Events/MessageSent.php`
  - `app/Http/Controllers/Api/ChatController.php`
  - `routes/channels.php`
  - `app/Providers/BroadcastServiceProvider.php`
  - `config/broadcasting.php`

- Env variables (add to your `.env`):
  - `BROADCAST_DRIVER=pusher`
  - `PUSHER_APP_ID`
  - `PUSHER_APP_KEY`
  - `PUSHER_APP_SECRET`
  - `PUSHER_APP_CLUSTER`

- Run migrations:

```bash
php artisan migrate
```

- Make sure you have the Pusher PHP server package (if not present):

```bash
composer require pusher/pusher-php-server
```

Backend API endpoints (authenticated via `sanctum`):
- `GET /api/chats/{userId}` — get conversation between authenticated user and `{userId}`
- `POST /api/chats/send` — send a message JSON `{ receiver_id, body }`

Broadcasting:
- Private channel used: `private-chat.{id}` (server uses `chat.{id}` — Laravel prefixes private channels automatically when using PrivateChannel).
- The `routes/channels.php` authorizes the `chat.{id}` channel by checking `auth()->id() === id`.

Next.js Frontend (minimal)

Install dependencies in your Next app:

```bash
npm install --save laravel-echo pusher-js axios
```

Example client (React hook + component):

```js
// lib/echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export default function createEcho(authToken, userId) {
  window.Pusher = Pusher;

  return new Echo({
    broadcaster: 'pusher',
    key: process.env.NEXT_PUBLIC_PUSHER_APP_KEY,
    cluster: process.env.NEXT_PUBLIC_PUSHER_APP_CLUSTER,
    forceTLS: true,
    auth: {
      headers: { Authorization: `Bearer ${authToken}` },
    },
  });
}

// components/Chat.js
import React, { useEffect, useState } from 'react';
import axios from 'axios';
import createEcho from '../lib/echo';

export default function Chat({ authToken, meId, otherUserId }) {
  const [messages, setMessages] = useState([]);
  const [body, setBody] = useState('');

  useEffect(() => {
    axios.get(`${process.env.NEXT_PUBLIC_API_URL}/chats/${otherUserId}`, {
      headers: { Authorization: `Bearer ${authToken}` },
    }).then(r => setMessages(r.data.data || []));

    const echo = createEcho(authToken, meId);
    const channel = echo.private(`chat.${meId}`);

    channel.listen('MessageSent', (payload) => {
      setMessages(prev => [...prev, payload]);
    });

    return () => {
      channel.stopListening('MessageSent');
      echo.leave(`chat.${meId}`);
    };
  }, [authToken, meId, otherUserId]);

  const send = async () => {
    await axios.post(`${process.env.NEXT_PUBLIC_API_URL}/chats/send`, {
      receiver_id: otherUserId,
      body,
    }, { headers: { Authorization: `Bearer ${authToken}` } });
    setBody('');
  };

  return (
    <div>
      <div style={{height:300, overflow:'auto'}}>
        {messages.map(m => (
          <div key={m.id || m.created_at}>
            <b>{m.sender?.name ?? m.sender_id}:</b> {m.body}
          </div>
        ))}
      </div>
      <input value={body} onChange={e => setBody(e.target.value)} />
      <button onClick={send}>Send</button>
    </div>
  );
}
```

Notes
- Frontend must authenticate to Laravel (we used Bearer token in examples). Adjust auth flow (cookie-based sanctum SPA or token) as needed.
- Ensure CORS and auth endpoints are configured so the Next.js app can request `api/*` and authenticate.
- On the Pusher dashboard, use the same cluster and keys and enable TLS.
