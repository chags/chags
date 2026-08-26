<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Não foi possível entrar — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            align-items: center;
            background: #f4f6f8;
            color: #17212b;
            display: flex;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            justify-content: center;
            margin: 0;
            min-height: 100vh;
            padding: 24px;
        }
        main {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .08);
            max-width: 480px;
            padding: 40px;
            text-align: center;
            width: 100%;
        }
        h1 { font-size: 24px; margin: 0 0 12px; }
        p { color: #52606d; line-height: 1.6; margin: 0 0 28px; }
        a {
            background: #17212b;
            border-radius: 10px;
            color: #fff;
            display: inline-block;
            font-weight: 600;
            padding: 12px 20px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main>
        <h1>Não foi possível concluir o acesso</h1>
        <p>O link de autenticação expirou, já foi utilizado ou a sessão não é mais válida. Inicie o acesso novamente.</p>
        <a href="{{ route('login') }}">Tentar novamente</a>
    </main>
</body>
</html>
