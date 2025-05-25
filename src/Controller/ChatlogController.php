<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ChatlogController extends AbstractController
{
    private string $uploadDir;

    public function __construct()
    {
        $this->uploadDir = dirname(__DIR__, 2) . '/public/uploads/chatlogs';
    }

    #[Route('/upload', name: 'app_upload')]
    public function upload(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('chatlog', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Fantasy Grounds Chatlog File',
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\File([
                        'maxSize' => '15M',
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
            
            // Create upload directory if it doesn't exist
            $filesystem = new Filesystem();
            if (!$filesystem->exists($this->uploadDir)) {
                $filesystem->mkdir($this->uploadDir);
            }

            // Generate unique filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $originalName = $file->getClientOriginalName();
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $newFilename = $timestamp . '_' . uniqid() . '.' . $extension;

            // Move the file to upload directory
            $file->move($this->uploadDir, $newFilename);
            
            $this->addFlash('success', 'File uploaded successfully!');
            return $this->redirectToRoute('app_chatlog_list');
        }

        return $this->render('chatlog/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/chatlogs', name: 'app_chatlog_list')]
    public function list(): Response
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->uploadDir)) {
            return $this->render('chatlog/list.html.twig', [
                'files' => []
            ]);
        }

        $finder = new Finder();
        $finder->files()
            ->in($this->uploadDir)
            ->sortByModifiedTime();

        $files = [];
        foreach ($finder as $file) {
            $files[] = [
                'name' => $file->getFilename(),
                'modified' => $file->getMTime(),
                'size' => $file->getSize(),
            ];
        }

        return $this->render('chatlog/list.html.twig', [
            'files' => $files
        ]);
    }

    #[Route('/chatlog/{filename}', name: 'app_chatlog_analyze')]
    public function analyze(string $filename): Response
    {
        $filePath = $this->uploadDir . '/' . $filename;
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The chatlog file does not exist');
        }

        return $this->render('chatlog/analyze.html.twig', [
            'filename' => $filename,
            'modified' => filemtime($filePath)
        ]);
    }
} 