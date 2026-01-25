<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Exception\ValidationException;
use App\Service\EmailVerificationService;
use App\Service\PasswordPolicyValidator;
use App\Service\PasswordResetService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;

/**
 * Controller for web authentication (login/register).
 */
class SecurityController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly PasswordResetService $passwordResetService,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly PasswordPolicyValidator $passwordPolicyValidator,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in, redirect to tasks
        if ($this->getUser()) {
            return $this->redirectToRoute('app_task_list');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
        FormLoginAuthenticator $formLoginAuthenticator,
    ): Response {
        // If user is already logged in, redirect to tasks
        if ($this->getUser()) {
            return $this->redirectToRoute('app_task_list');
        }

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');
            $csrfToken = (string) $request->request->get('_csrf_token', '');

            $errors = [];

            // Validate CSRF token
            if (!$this->isCsrfTokenValid('register', $csrfToken)) {
                $errors[] = 'Invalid security token. Please try again.';
            }

            // Validate email
            if (empty($email)) {
                $errors[] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif ($this->userService->findByEmail($email) !== null) {
                $errors[] = 'An account with this email already exists.';
            }

            // Validate password using policy
            if (empty($password)) {
                $errors[] = 'Password is required.';
            } else {
                $policyErrors = $this->passwordPolicyValidator->validate($password, $email);
                foreach ($policyErrors as $policyError) {
                    $errors[] = $policyError;
                }
            }

            // Validate password confirmation
            if ($password !== $passwordConfirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                // Create the user
                $user = $this->userService->register($email, $password);

                // Auto-login the user after registration
                $response = $userAuthenticator->authenticateUser(
                    $user,
                    $formLoginAuthenticator,
                    $request,
                );

                if ($response !== null) {
                    return $response;
                }

                $this->addFlash('success', 'Welcome! Your account has been created successfully.');

                return $this->redirectToRoute('app_task_list');
            }

            return $this->render('security/register.html.twig', [
                'errors' => $errors,
                'email' => $email,
                'requirements' => $this->passwordPolicyValidator->getRequirements(),
            ]);
        }

        return $this->render('security/register.html.twig', [
            'requirements' => $this->passwordPolicyValidator->getRequirements(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        // This method will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('forgot_password', $token)) {
                return $this->render('security/forgot-password.html.twig', [
                    'errors' => ['Invalid CSRF token'],
                    'email' => $request->request->get('email', ''),
                ]);
            }

            $email = $request->request->get('email', '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->render('security/forgot-password.html.twig', [
                    'errors' => ['Please enter a valid email address'],
                    'email' => $email,
                ]);
            }

            $this->passwordResetService->requestReset($email);

            return $this->render('security/forgot-password.html.twig', [
                'success' => true,
                'email' => $email,
            ]);
        }

        return $this->render('security/forgot-password.html.twig');
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request): Response
    {
        $token = $request->query->get('token', '');

        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('reset_password', $csrfToken)) {
                return $this->render('security/reset-password.html.twig', [
                    'errors' => ['Invalid CSRF token'],
                    'token' => $token,
                ]);
            }

            $token = $request->request->get('token', '');
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');

            if ($password !== $passwordConfirm) {
                return $this->render('security/reset-password.html.twig', [
                    'errors' => ['Passwords do not match'],
                    'token' => $token,
                ]);
            }

            try {
                $this->passwordResetService->resetPassword($token, $password);
                $this->addFlash('success', 'Your password has been reset. Please log in with your new password.');
                return $this->redirectToRoute('app_login');
            } catch (ValidationException $e) {
                return $this->render('security/reset-password.html.twig', [
                    'errors' => [$e->getMessage()],
                    'token' => $token,
                ]);
            }
        }

        // Validate token on GET request
        if (!empty($token) && !$this->passwordResetService->validateToken($token)) {
            return $this->render('security/reset-password.html.twig', [
                'errors' => ['Invalid or expired reset link. Please request a new one.'],
                'token' => '',
                'tokenInvalid' => true,
            ]);
        }

        return $this->render('security/reset-password.html.twig', [
            'token' => $token,
            'requirements' => $this->passwordPolicyValidator->getRequirements(),
        ]);
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token): Response
    {
        try {
            $this->emailVerificationService->verifyEmail($token);
            return $this->render('security/verify-email.html.twig', [
                'success' => true,
            ]);
        } catch (ValidationException $e) {
            return $this->render('security/verify-email.html.twig', [
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
