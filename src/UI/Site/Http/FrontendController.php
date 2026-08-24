<?php

namespace App\UI\Site\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontendController extends AbstractController
{
    #[Route('/', name: 'public_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('site/public.html.twig', ['slug' => 'startseite']);
    }

    #[Route('/seite/{slug}', name: 'public_page', requirements: ['slug' => '.+'], methods: ['GET'])]
    public function page(string $slug): Response
    {
        return $this->render('site/public.html.twig', ['slug' => $slug]);
    }

    #[Route('/admin/{path}', name: 'admin_app', requirements: ['path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function admin(): Response
    {
        return $this->render('site/admin.html.twig');
    }
}
