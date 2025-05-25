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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/chatlog')]
class ChatlogController extends AbstractController
{
    private string $uploadDir;
    private ChatlogAnalyzer $chatlogAnalyzer;
    private $security;

    public function __construct(ChatlogAnalyzer $chatlogAnalyzer, Security $security)
    {
        $this->uploadDir = dirname(__DIR__, 2) . '/public/uploads/chatlogs';
        $this->chatlogAnalyzer = $chatlogAnalyzer;
        $this->security = $security;
    }

    #[Route('/upload', name: 'app_chatlog_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('GET')) {
            return $this->render('chatlog/upload.html.twig');
        }

        $file = $request->files->get('chatlog');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getClientMimeType() !== 'text/plain') {
            return $this->json(['error' => 'Only text files are allowed'], Response::HTTP_BAD_REQUEST);
        }

        $username = $user->getUserIdentifier();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/var/uploads/' . $username;
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = uniqid() . '.txt';
        $file->move($uploadDir, $filename);

        return $this->json(['success' => true, 'filename' => $filename]);
    }

    #[Route('/analyze/{filename}', name: 'app_chatlog_analyze', methods: ['GET'])]
    public function analyze(string $filename): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $username = $user->getUserIdentifier();
        $filepath = $this->getParameter('kernel.project_dir') . '/var/uploads/' . $username . '/' . $filename;

        if (!file_exists($filepath)) {
            return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $analysis = $this->chatlogAnalyzer->analyze($filepath);
        return $this->json($analysis);
    }

    #[Route('/list', name: 'app_chatlog_list', methods: ['GET'])]
    public function list(): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $username = $user->getUserIdentifier();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/var/uploads/' . $username;

        if (!file_exists($uploadDir)) {
            return $this->json(['files' => []]);
        }

        $files = array_map(function($file) use ($username) {
            return [
                'name' => $file,
                'size' => filesize($this->getParameter('kernel.project_dir') . '/var/uploads/' . $username . '/' . $file),
                'uploaded' => date('Y-m-d H:i:s', filemtime($this->getParameter('kernel.project_dir') . '/var/uploads/' . $username . '/' . $file))
            ];
        }, array_diff(scandir($uploadDir), ['.', '..']));

        return $this->json(['files' => $files]);
    }

    #[Route('/delete/{filename}', name: 'app_chatlog_delete', methods: ['DELETE'])]
    public function delete(string $filename): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $username = $user->getUserIdentifier();
        $filepath = $this->getParameter('kernel.project_dir') . '/var/uploads/' . $username . '/' . $filename;

        if (!file_exists($filepath)) {
            return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        unlink($filepath);
        return $this->json(['success' => true]);
    }

    #[Route('/chatlog/{filename}', name: 'app_chatlog_analyze')]
    public function analyzeOld(string $filename): Response
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