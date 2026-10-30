<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class CitizenSecurityController extends AbstractController
{
    #[Route('/citizen/login', name: 'citizen_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        return $this->render('security/login.html.twig', [
            'lastUsername' => $authentication->getLastUsername(),
            'error' => $authentication->getLastAuthenticationError(),
        ]);
    }

    #[Route('/citizen/logout', name: 'citizen_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('La déconnexion est traitée par le pare-feu Symfony.');
    }
}
