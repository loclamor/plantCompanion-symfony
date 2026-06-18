<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert la coquille du SPA Vue pour toutes les URLs sous /app : vue-router gère
 * ensuite le routing côté client (mode history, base /app). Servie même non
 * authentifié (le SPA affiche son propre écran de login ; l'API garde l'accès).
 */
final class SpaController extends AbstractController
{
    #[Route('/app{vue}', name: 'app_spa', requirements: ['vue' => '.*'], defaults: ['vue' => ''], priority: -10)]
    public function index(): Response
    {
        return $this->render('spa.html.twig');
    }
}
