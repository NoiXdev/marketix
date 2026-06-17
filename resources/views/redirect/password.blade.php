<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password Protected Link</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #0f172a;
        }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 1px 3px rgba(0,0,0,.07);
        }
        .icon {
            width: 44px; height: 44px;
            background: #eef2ff;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem;
        }
        h1 { font-size: 1.125rem; font-weight: 600; margin-bottom: .375rem; }
        p  { font-size: .875rem; color: #64748b; margin-bottom: 1.5rem; }
        label { display: block; font-size: .8125rem; font-weight: 500; margin-bottom: .375rem; }
        input[type=password] {
            width: 100%; padding: .5rem .75rem;
            border: 1px solid #cbd5e1; border-radius: 6px;
            font-size: .875rem; color: #0f172a;
            outline: none; transition: border-color .15s;
        }
        input[type=password]:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .error { font-size: .75rem; color: #dc2626; margin-top: .375rem; }
        button {
            margin-top: 1.25rem; width: 100%;
            background: #4f46e5; color: #fff;
            border: none; border-radius: 6px;
            padding: .5625rem 1rem; font-size: .875rem; font-weight: 600;
            cursor: pointer; transition: background .15s;
        }
        button:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <h1>Password required</h1>
        <p>This link is protected. Enter the password to continue.</p>

        <form method="POST" action="/{{ $slug }}">
            @csrf
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autofocus autocomplete="off">
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
            <button type="submit">Continue</button>
        </form>
    </div>
</body>
</html>
