<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert la coquille du SPA Vue à la racine : vue-router gère le routing côté
 * client (mode history). Catch-all de priorité basse, excluant l'API,
 * l'inscription Twig et les chemins internes Symfony. Servie même non
 * authentifié (le SPA affiche son propre écran de login ; l'API garde l'accès).
 */
final class SpaController extends AbstractController
{
    #[Route(
        '/{vue}',
        name: 'app_spa',
        requirements: ['vue' => '^(?!api|register|_(profiler|wdt)|build|media|uploads).*'],
        defaults: ['vue' => ''],
        priority: -10,
    )]
    public function index(): Response
    {
        return $this->render('spa.html.twig');
    }
}
