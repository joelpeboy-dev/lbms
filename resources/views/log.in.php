<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Log in | Library Book Monitoring System</title>
	<style>
		:root { --blue: #1469f5; --navy: #1d2940; --ink: #263143; --muted: #7b8490; --paper: #0b1b2b; }
		* { box-sizing: border-box; }
		body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; overflow: hidden; background: var(--paper); color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; }
		main { display: grid; grid-template-columns: 1fr 1fr; width: min(100%, 710px); min-height: 330px; overflow: hidden; background: #fff; border-radius: 7px; box-shadow: 0 18px 45px #0005; animation: reveal .55s ease both; }
		.lightning-backdrop { position: fixed; z-index: 0; inset: 0; overflow: hidden; pointer-events: none; }
		.lightning-backdrop svg { position: absolute; width: 100%; height: 100%; inset: 0; overflow: visible; }
		.lightning-backdrop path { fill: none; stroke: #9ccaff; stroke-linecap: round; stroke-linejoin: round; stroke-width: 3; filter: drop-shadow(0 0 8px #6bb5ff) drop-shadow(0 0 20px #4b8dff); opacity: 0; animation: lightning-travel 8s ease-in-out infinite; }
		.lightning-backdrop path:nth-child(2) { stroke: #e0f3ff; stroke-width: 1.5; animation-delay: 3.7s; }
		@keyframes lightning-travel {
			0%, 9%, 16%, 100% { opacity: 0; transform: translateY(-18px); }
			10%, 12% { opacity: .75; }
			13% { opacity: .12; }
			14% { opacity: .55; }
			15% { opacity: 0; transform: translateY(34px); }
		}
		main { position: relative; z-index: 1; }
		.brand-panel { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 32px 28px; background: var(--navy); color: white; text-align: center; }
		.login-art { display: block; width: min(100%, 270px); height: auto; border-radius: 14px; box-shadow: 0 16px 36px #0005; }
		.cap { position: relative; width: 40px; height: 26px; margin-bottom: 21px; background: #33226e; clip-path: polygon(50% 0, 100% 38%, 50% 77%, 0 38%); }
		.cap::after { content: ''; position: absolute; width: 4px; height: 19px; right: 5px; top: 13px; background: #f5a623; transform: rotate(-35deg); transform-origin: top; }
		.brand-panel h2 { max-width: 240px; margin: 0; font-size: 20px; line-height: 1.25; } .brand-panel p { margin: 10px 0 0; color: #aab7ca; font-size: 11px; }
		.login-panel { padding: 38px 36px 28px; background: #fff; } .mark { color: #596273; font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
		.login-banner { display: block; width: 100%; height: 104px; margin: 0 0 18px; border-radius: 7px; background: #0b1b2b; object-fit: cover; object-position: center 58%; }
		h1 { margin: 8px 0 5px; font-size: 22px; line-height: 1.1; }
		.intro { margin: 0 0 19px; color: var(--muted); line-height: 1.5; font-size: 12px; }
		label { display: block; margin: 12px 0 5px; color: #404957; font-size: 12px; font-weight: 800; }
		input { width: 100%; padding: 9px 10px; border: 1px solid #cdd2da; border-radius: 2px; background: #fff; color: var(--ink); font: 12px inherit; }
		input:focus { outline: 2px solid #1469f533; border-color: var(--blue); }
		.password-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
		.password-row label { margin-bottom: 7px; }
		.show-password { display: flex; align-items: center; gap: 5px; margin: 8px 0 7px; color: var(--muted); font-size: 11px; font-weight: 400; cursor: pointer; }
		.show-password input { width: auto; accent-color: var(--blue); }
		button { width: 100%; margin-top: 14px; padding: 11px; border: 0; border-radius: 3px; background: var(--blue); color: white; font: 800 12px inherit; cursor: pointer; transition: background .2s, transform .2s, box-shadow .3s; }
		button:hover { transform: translateY(-2px); background: #0d54cb; box-shadow: 0 0 20px #1469f580, 0 0 40px #1469f540; }
		button:active { transform: translateY(0); }
		.error { margin: 0 0 10px; padding: 8px 10px; border-left: 3px solid #e23d4f; background: #fff0f2; color: #a22132; font-size: 11px; }
		a { color: var(--blue); font-weight: 800; transition: all .3s ease; }
		a:hover { text-shadow: 0 0 10px #1469f580; transform: translateX(2px); display: inline-block; }
		.footer { margin: 18px 0 0; color: #8b929c; font-size: 10px; text-align: center; }
		@keyframes reveal { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: none; } }
		@keyframes slideInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
		@keyframes slideInLeft { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
		@keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
		@keyframes blueGlow { 0%, 100% { text-shadow: 0 0 10px #1469f5, 0 0 20px #1469f580, 0 0 30px #1469f540; } 50% { text-shadow: 0 0 20px #1469f5, 0 0 40px #1469f580, 0 0 60px #1469f540, 0 0 80px #1469f520; } }
		@keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-8px); } }
		@keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(20, 105, 245, 0.7); } 50% { box-shadow: 0 0 0 10px rgba(20, 105, 245, 0); } }
		@keyframes shimmer { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
		.brand-panel h2 span { display: inline-block; animation: slideInUp .6s ease both; }
		.brand-panel h2 span:nth-child(1) { animation-delay: 0s; }
		.brand-panel h2 span:nth-child(2) { animation-delay: .1s; }
		.brand-panel h2 span:nth-child(3) { animation-delay: .2s; }
		.brand-panel h2 { animation: blueGlow 3s ease-in-out infinite 0.7s; }
		.login-art { animation: float 3s ease-in-out infinite; }
		.login-banner { animation: fadeInUp .8s ease 0.3s both; }
		.login-panel { animation: slideInLeft .7s ease 0.2s both; }
		label { animation: fadeInUp .5s ease both; }
		label:nth-of-type(1) { animation-delay: 0.4s; }
		label:nth-of-type(2) { animation-delay: 0.5s; }
		label:nth-of-type(3) { animation-delay: 0.6s; }
		input { animation: shimmer 2s ease-in-out infinite; }
		input:focus { animation: none; box-shadow: inset 0 0 15px #1469f520, 0 0 20px #1469f540; }
		button { animation: pulse 2.5s infinite 1.2s; }
		@media (max-width: 600px) { body { overflow-y: auto; } main { grid-template-columns: 1fr; width: min(100%, 390px); } .brand-panel { min-height: 0; padding: 24px; } .login-art { width: min(100%, 220px); } .login-panel { padding: 30px 26px 24px; } .login-banner { height: 112px; } }
		@media (max-height: 720px) and (min-width: 601px) { body { align-items: start; overflow-y: auto; padding-top: 16px; padding-bottom: 16px; } }
		@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } .lightning-backdrop path { animation: none; opacity: .12; } }
	</style>
	<link rel="stylesheet" href="<?php echo e(asset('css/library-theme.css')); ?>">
</head>
<body>
	<div class="lightning-backdrop" aria-hidden="true">
		<svg viewBox="0 0 1440 900" preserveAspectRatio="none" focusable="false">
			<path d="M280 -40 210 130 282 112 160 315 235 293 100 510 180 487 62 710" />
			<path d="M1190 -60 1110 150 1180 130 1050 350 1128 324 1000 545" />
		</svg>
	</div>
	<main>
		<section class="brand-panel">
			<img class="login-art" src="<?php echo e(asset('images/library-login-art.svg')); ?>" alt="An open book glowing in a library, with bookshelves, a starry window, and a shooting star.">
			<h2><span>LIBRARY BOOK</span><br><span>MONITORING</span> <span>SYSTEM</span></h2>
		</section>
		<section class="login-panel">
		<img class="login-banner" src="<?php echo e($loginImageUrl); ?>" alt="Library welcome banner">
		<div class="mark">Library Book Monitoring System</div>
		<h1>Login to your account</h1>
		<p class="intro">Enter your account details to continue.</p>

		<?php if ($errors->any()): ?>
			<div class="error"><?php echo e($errors->first()); ?></div>
		<?php endif; ?>
		<?php if (session('status')): ?>
			<div class="status" role="status"><?php echo e(session('status')); ?></div>
		<?php endif; ?>

		<form method="POST" action="<?php echo e(route('login.store')); ?>">
			<?php echo csrf_field(); ?>

			<label for="name">Username</label>
			<input id="name" name="name" type="text" value="<?php echo e(old('name')); ?>" autocomplete="name" required>

			<div class="password-row">
				<label for="password">Password</label>
				<label class="show-password"><input id="show-password" type="checkbox"> Show password</label>
			</div>
			<input id="password" name="password" type="password" autocomplete="current-password" required>
			<p class="intro" style="margin: 9px 0 0; text-align: right;"><a href="<?php echo e(route('password.request')); ?>">Forgot your password?</a></p>

			<button type="submit">Log in</button>
		</form>
		<p class="intro" style="margin: 16px 0 0; text-align: center;">New student? <a href="<?php echo e(route('register')); ?>">Create an account</a></p>
		<p class="footer">&copy; 2026 Library Book Monitoring System</p>
		</section>
	</main>
	<script>
		document.getElementById('show-password').addEventListener('change', function () {
			document.getElementById('password').type = this.checked ? 'text' : 'password';
		});
	</script>
</body>
</html>
