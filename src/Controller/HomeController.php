<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/upload', name: 'app_upload')]
    public function upload(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('chatlog', FileType::class, [
                'label' => 'Fantasy Grounds Chatlog File',
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'text/html',
                            'application/xhtml+xml',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid HTML file',
                    ])
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('chatlog')->getData();
            
            // TODO: Process the file and extract dice roll statistics
            
            $this->addFlash('success', 'File uploaded successfully!');
            return $this->redirectToRoute('app_upload');
        }

        return $this->render('chatlog/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
} 