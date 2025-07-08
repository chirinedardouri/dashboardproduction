<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Form\ChangePwsdFormType;
use App\Form\ResetPwsdFormType;
use App\Form\UserFormType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Security\LoginFormAuthenticator;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ForceLoginController extends AbstractController
{
    #[Route('/force-login-chirine', name: 'force_login_chirine')]
    public function loginAsChirine(
        Request $request,
        UserRepository $userRepository,
        LoginFormAuthenticator $authenticator,
        UserAuthenticatorInterface $userAuthenticator
    ): Response {
        $user = $userRepository->findOneBy(['username' => 'admin']);

        if (!$user) {
            return new Response("Utilisateur 'chirine' introuvable", 404);
        }

        $userAuthenticator->authenticateUser($user, $authenticator, $request);

        return $this->redirectToRoute('app_admin_users'); // à adapter avec une vraie route existante
    }
}