<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PasswordReset.php';
require_once __DIR__ . '/../services/MailerService.php';

class AuthController extends BaseController {
	private User $userModel;
	private PasswordReset $passwordResetModel;
	private MailerService $mailer;

	public function __construct() {
		$this->userModel = new User();
		$this->passwordResetModel = new PasswordReset();
		$this->mailer = new MailerService();
	}

	public function showLogin(): void {
		$this->requireGuest();
		$this->render('auth/login', [
			'title' => 'Đăng nhập',
			'csrfToken' => $this->csrfToken(),
			'error' => $this->flash('error'),
			'success' => $this->flash('success'),
			'oldEmail' => $this->old('email'),
		], 'auth');
	}

	public function login(): void {
		$this->requireGuest();

		$token = $_POST['_csrf'] ?? null;
		if (!$this->verifyCsrf($token)) {
			$this->flash('error', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
			$this->redirect('/login');
		}

		$email = trim((string) ($_POST['email'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		$this->setOldInput(['email' => $email]);

		if ($email === '' || $password === '') {
			$this->flash('error', 'Vui lòng nhập email và mật khẩu.');
			$this->redirect('/login');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->flash('error', 'Email không hợp lệ.');
			$this->redirect('/login');
		}

		try {
			$user = $this->userModel->findByEmail($email);
		} catch (Throwable $e) {
			Logger::error('Login lookup error', ['message' => $e->getMessage()]);
			$this->flash('error', 'Hệ thống đang bận. Vui lòng thử lại sau.');
			$this->redirect('/login');
			return;
		}

		if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
			$this->flash('error', 'Email hoặc mật khẩu không đúng.');
			$this->redirect('/login');
		}

		if ((int) ($user['is_active'] ?? 0) !== 1) {
			$this->flash('error', 'Tài khoản của bạn đã bị khóa.');
			$this->redirect('/login');
		}

		session_regenerate_id(true);
		$_SESSION['user'] = [
			'id' => (int) $user['id'],
			'username' => (string) $user['username'],
			'email' => (string) $user['email'],
			'role' => (string) ($user['role'] ?? 'user'),
		];

		$this->clearOldInput();
		$this->redirect('/');
	}

	public function showRegister(): void {
		$this->requireGuest();
		$this->render('auth/register', [
			'title' => 'Đăng ký',
			'csrfToken' => $this->csrfToken(),
			'error' => $this->flash('error'),
			'oldUsername' => $this->old('username'),
			'oldEmail' => $this->old('email'),
		], 'auth');
	}

	public function register(): void {
		$this->requireGuest();

		$token = $_POST['_csrf'] ?? null;
		if (!$this->verifyCsrf($token)) {
			$this->flash('error', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
			$this->redirect('/register');
		}

		$username = trim((string) ($_POST['username'] ?? ''));
		$email = trim((string) ($_POST['email'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		$confirm = (string) ($_POST['confirm_password'] ?? '');

		$this->setOldInput([
			'username' => $username,
			'email' => $email,
		]);

		if ($username === '' || $email === '' || $password === '' || $confirm === '') {
			$this->flash('error', 'Vui lòng điền đầy đủ các trường bắt buộc.');
			$this->redirect('/register');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->flash('error', 'Email không đúng định dạng.');
			$this->redirect('/register');
		}

		if (strlen($username) < 3 || strlen($username) > 50) {
			$this->flash('error', 'Tên đăng nhập cần từ 3 đến 50 ký tự.');
			$this->redirect('/register');
		}

		if ($password !== $confirm) {
			$this->flash('error', 'Mật khẩu nhập lại không khớp.');
			$this->redirect('/register');
		}

		if (strlen($password) < 8) {
			$this->flash('error', 'Mật khẩu tối thiểu 8 ký tự.');
			$this->redirect('/register');
		}

		try {
			if ($this->userModel->findByUsername($username) !== null) {
				$this->flash('error', 'Tên đăng nhập đã tồn tại.');
				$this->redirect('/register');
			}

			if ($this->userModel->findByEmail($email) !== null) {
				$this->flash('error', 'Email đã được sử dụng.');
				$this->redirect('/register');
			}
		} catch (Throwable $e) {
			Logger::error('Register duplicate-check error', ['message' => $e->getMessage()]);
			$this->flash('error', 'Hệ thống đang bận. Vui lòng thử lại sau.');
			$this->redirect('/register');
			return;
		}

		$passwordHash = password_hash($password, PASSWORD_DEFAULT);
		try {
			$userId = $this->userModel->createUser([
				'username' => $username,
				'email' => $email,
				'password_hash' => $passwordHash,
				'role' => 'user',
				'is_active' => 1,
			]);
		} catch (Throwable $e) {
			Logger::error('Register create-user error', ['message' => $e->getMessage()]);
			$this->flash('error', 'Không thể tạo tài khoản lúc này. Vui lòng thử lại.');
			$this->redirect('/register');
			return;
		}

		session_regenerate_id(true);
		$_SESSION['user'] = [
			'id' => $userId,
			'username' => $username,
			'email' => $email,
			'role' => 'user',
		];

		$this->clearOldInput();
		$this->redirect('/');
	}

	public function logout(): void {
		$token = $_POST['_csrf'] ?? null;
		if (!$this->verifyCsrf($token)) {
			http_response_code(419);
			echo 'CSRF token invalid';
			return;
		}

		unset($_SESSION['user']);
		session_regenerate_id(true);
//		$this->flash('success', 'Bạn đã đăng xuất thành công.');
		$this->redirect('/login');
	}

	public function showForgotPassword(): void {
		$this->requireGuest();
		$this->render('auth/forgot_password', [
			'title' => 'Quên mật khẩu',
			'csrfToken' => $this->csrfToken(),
			'error' => $this->flash('error'),
			'oldEmail' => $this->old('email'),
		], 'auth');
	}

	public function sendResetLink(): void {
		$this->requireGuest();

		$token = $_POST['_csrf'] ?? null;
		if (!$this->verifyCsrf($token)) {
			$this->flash('error', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
			$this->redirect('/forgot-password');
		}

		$email = trim((string) ($_POST['email'] ?? ''));
		$this->setOldInput(['email' => $email]);

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->flash('error', 'Email không đúng định dạng.');
			$this->redirect('/forgot-password');
		}

		try {
			$user = $this->userModel->findByEmail($email);
			if ($user !== null && (int) ($user['is_active'] ?? 0) === 1) {
				$rawToken = $this->passwordResetModel->createToken((int) $user['id'], 30);
				$resetUrl = rtrim((string) env('APP_URL', ''), '/');
				if ($resetUrl === '') {
					$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
					$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
					$resetUrl = $scheme . '://' . $host;
				}

				$resetUrl .= BASE_URL . '/reset-password/' . urlencode($rawToken);
				$this->mailer->sendPasswordReset((string) $user['email'], (string) $user['username'], $resetUrl);
			}
		} catch (Throwable $e) {
			Logger::error('Forgot-password error', ['message' => $e->getMessage()]);
		}

		$this->clearOldInput();
		$this->render('auth/reset_sent', [
			'title' => 'Thông báo',
		], 'auth');
	}

	public function showResetPassword(string $token): void {
		$this->requireGuest();
		try {
			$record = $this->passwordResetModel->findValidByRawToken($token);
		} catch (Throwable $e) {
			Logger::error('Reset-password lookup error', ['message' => $e->getMessage()]);
			$this->flash('error', 'Hệ thống đang bận. Vui lòng thử lại sau.');
			$this->redirect('/forgot-password');
			return;
		}

		if ($record === null) {
			$this->flash('error', 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
			$this->redirect('/forgot-password');
		}

		$this->render('auth/reset_password', [
			'title' => 'Đặt lại mật khẩu',
			'csrfToken' => $this->csrfToken(),
			'token' => $token,
			'error' => $this->flash('error'),
		], 'auth');
	}

	public function resetPassword(string $token): void {
		$this->requireGuest();

		$csrf = $_POST['_csrf'] ?? null;
		if (!$this->verifyCsrf($csrf)) {
			$this->flash('error', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
			$this->redirect('/reset-password/' . urlencode($token));
		}

		try {
			$record = $this->passwordResetModel->findValidByRawToken($token);
		} catch (Throwable $e) {
			Logger::error('Reset-password verify-token error', ['message' => $e->getMessage()]);
			$this->flash('error', 'Hệ thống đang bận. Vui lòng thử lại sau.');
			$this->redirect('/forgot-password');
			return;
		}
		if ($record === null) {
			$this->flash('error', 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
			$this->redirect('/forgot-password');
		}

		$password = (string) ($_POST['password'] ?? '');
		$confirm = (string) ($_POST['confirm_password'] ?? '');

		if ($password === '' || $confirm === '') {
			$this->flash('error', 'Vui lòng nhập đủ mật khẩu mới.');
			$this->redirect('/reset-password/' . urlencode($token));
		}

		if ($password !== $confirm) {
			$this->flash('error', 'Mật khẩu nhập lại không khớp.');
			$this->redirect('/reset-password/' . urlencode($token));
		}

		if (strlen($password) < 8) {
			$this->flash('error', 'Mật khẩu tối thiểu 8 ký tự.');
			$this->redirect('/reset-password/' . urlencode($token));
		}

		try {
			$passwordHash = password_hash($password, PASSWORD_DEFAULT);
			$this->userModel->updatePassword((int) $record['user_id'], $passwordHash);
			$this->passwordResetModel->deleteByRawToken($token);
		} catch (Throwable $e) {
			Logger::error('Reset-password update error', ['message' => $e->getMessage()]);
			$this->flash('error', 'Không thể cập nhật mật khẩu lúc này. Vui lòng thử lại.');
			$this->redirect('/forgot-password');
			return;
		}
		$this->flash('success', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.');
		$this->redirect('/login');
	}
}

