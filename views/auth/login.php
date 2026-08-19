<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1e2125 0%, #2c3e50 100%); min-height: 100vh; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        .login-card { background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 420px; margin: 0 auto; overflow: hidden; }
        .brand-bar { background: #DA291C; height: 5px; }
        .brand-header { background: linear-gradient(135deg, #1e2125, #2c3e50); padding: 2rem; text-align: center; color: #fff; }
        .brand-header i { font-size: 3rem; margin-bottom: 1rem; }
        .pin-input { font-size: 2rem; font-weight: 700; letter-spacing: 0.5rem; text-align: center; border: 2px solid #dee2e6; border-radius: 12px; padding: 1rem; transition: border-color 0.3s, box-shadow 0.3s; }
        .pin-input:focus { border-color: #DA291C; box-shadow: 0 0 0 4px rgba(218, 41, 28, 0.15); outline: none; }
        .btn-login { background: #DA291C; border: none; font-weight: 700; padding: 0.8rem; border-radius: 12px; font-size: 1rem; transition: all 0.3s; }
        .btn-login:hover { background: #b02116; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(218, 41, 28, 0.3); }
        .error-msg { background: #fff8f8; border: 1px solid #f5c2c7; color: #dc3545; border-radius: 10px; padding: 0.75rem; font-size: 0.9rem; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-4">
    <div class="login-card w-100">
        <div class="brand-bar"></div>
        <div class="brand-header">
            <i class="fas fa-train"></i>
            <h3 class="fw-bold mb-0"><?php echo APP_NAME; ?></h3>
            <small class="opacity-75">Container Tracking Portal</small>
        </div>
        <div class="p-4">
            <?php if (!empty($error)): ?>
                <div class="error-msg mb-3 text-center"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="/login">
                <div class="mb-3">
                    <label class="form-label fw-bold text-center d-block">Enter Your PIN</label>
                    <input type="password" name="pin" class="form-control pin-input" autofocus required maxlength="32" autocomplete="off" placeholder="****">
                </div>
                <button type="submit" class="btn btn-login w-100 text-white"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
            </form>
        </div>
        <div class="text-center pb-3 px-4"><small class="text-muted">Authorized personnel only. Contact your administrator for PIN access.</small></div>
    </div>
</body>
</html>
