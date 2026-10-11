<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // If already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_user_dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email     = trim($request->request->get('email', ''));
            $fullName  = trim($request->request->get('fullName', ''));
            $password  = $request->request->get('password', '');
            $password2 = $request->request->get('password_confirm', '');

            // Validation
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Veuillez entrer une adresse email valide.';
            } elseif (empty($password) || strlen($password) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caractères.';
            } elseif ($password !== $password2) {
                $error = 'Les mots de passe ne correspondent pas.';
            } else {
                // Check if email is already taken
                $existing = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existing) {
                    $error = 'Cette adresse email est déjà utilisée.';
                } else {
                    // Create user
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFullName($fullName ?: null);
                    $user->setRoles([]); // ROLE_USER is added automatically

                    $hashedPassword = $passwordHasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);

                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre compte a été créé. Vous pouvez maintenant vous connecter !');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('registration/register.html.twig', [
            'error' => $error,
        ]);
    }
}
