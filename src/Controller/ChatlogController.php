<?php

namespace App\Controller;

use App\Form\ChatlogFileType;
use App\Service\ChatlogAnalyzer;
use App\Validator\Constraints\ChatlogFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChatlogController extends AbstractController
{
    private string $uploadDir;
    private ChatlogAnalyzer $chatlogAnalyzer;

    public function __construct(ChatlogAnalyzer $chatlogAnalyzer)
    {
        $this->uploadDir = dirname(__DIR__, 2) . '/public/uploads/chatlogs';
        $this->chatlogAnalyzer = $chatlogAnalyzer;
    }

    #[Route('/upload', name: 'app_upload')]
    public function upload(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('chatlog', ChatlogFileType::class, [
                'label' => 'Fantasy Grounds Chatlog File',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a file to upload'
                    ]),
                    new ChatlogFile()
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $file = $form->get('chatlog')->getData();
                if ($file) {
                    $errors = $form->get('chatlog')->getErrors();
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getMessage();
                    }
                    
                    $this->addFlash('error', sprintf(
                        'Debug info: Original name: %s, Mime type: %s, Extension: %s, Errors: %s',
                        $file->getClientOriginalName(),
                        $file->getMimeType(),
                        pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION),
                        implode(', ', $errorMessages)
                    ));
                }
            }

            if ($form->isValid()) {
                $file = $form->get('chatlog')->getData();
                
                // Create upload directory if it doesn't exist
                $filesystem = new Filesystem();
                try {
                    if (!$filesystem->exists($this->uploadDir)) {
                        $filesystem->mkdir($this->uploadDir, 0775);
                    }
                } catch (IOException $e) {
                    $this->addFlash('error', 'Unable to create upload directory. Please contact the administrator.');
                    return $this->redirectToRoute('app_upload');
                }

                // Check if directory is writable
                if (!is_writable($this->uploadDir)) {
                    $this->addFlash('error', 'Upload directory is not writable. Please contact the administrator.');
                    return $this->redirectToRoute('app_upload');
                }

                // Generate unique filename with timestamp
                $timestamp = date('Y-m-d_H-i-s');
                $originalName = $file->getClientOriginalName();
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $newFilename = $timestamp . '_' . uniqid() . '.' . $extension;

                try {
                    // Move the file to upload directory
                    $file->move($this->uploadDir, $newFilename);
                    $this->addFlash('success', 'File uploaded successfully!');
                    return $this->redirectToRoute('app_chatlog_list');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to save the uploaded file. Please try again or contact the administrator.');
                    return $this->redirectToRoute('app_upload');
                }
            }
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

        $content = file_get_contents($filePath);
        $analyzer = new ChatlogAnalyzer();
        $analysis = $analyzer->analyze($content);

        return $this->render('chatlog/analyze.html.twig', [
            'filename' => $filename,
            'modified' => filemtime($filePath),
            'analysis' => $analysis
        ]);
    }

    #[Route('/chatlog/{filename}/character/{character}', name: 'app_chatlog_character')]
    public function character(string $filename, string $character): Response
    {
        $filePath = $this->uploadDir . '/' . $filename;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The chatlog file does not exist');
        }

        $content = file_get_contents($filePath);
        $analysis = $this->chatlogAnalyzer->analyze($content);
        
        if (!isset($analysis['totals']['characters'][$character])) {
            throw $this->createNotFoundException('Character not found');
        }

        return $this->render('chatlog/character.html.twig', [
            'character' => $character,
            'character_data' => $analysis['totals']['characters'][$character],
            'filename' => $filename
        ]);
    }
} 