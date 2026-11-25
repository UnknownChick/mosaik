<?php

namespace App\Controller;

use App\Entity\Bookmark;
use App\Entity\Folder;
use App\Form\BookmarkType;
use App\Form\FolderType;
use App\Repository\BookmarkRepository;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LibraryController extends AbstractController
{
    public function __construct(
        private readonly FolderRepository $folders,
        private readonly BookmarkRepository $bookmarks,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'app_library', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderLibrary('all');
    }

    #[Route('/sans-dossier', name: 'app_library_uncategorized', methods: ['GET'])]
    public function uncategorized(): Response
    {
        return $this->renderLibrary('uncategorized');
    }

    #[Route('/dossiers/{id}', name: 'app_folder_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function folder(Folder $folder): Response
    {
        return $this->renderLibrary('folder', $folder);
    }

    #[Route('/liens/nouveau', name: 'app_bookmark_new', methods: ['GET', 'POST'])]
    public function newBookmark(Request $request): Response
    {
        $currentFolder = $this->folderFromQuery($request, 'folder');
        $navigation = $this->navigation('form', $currentFolder);
        $bookmark = (new Bookmark())->setFolder($currentFolder);
        $form = $this->createForm(BookmarkType::class, $bookmark, [
            'folder_choices' => $navigation['folder_choices'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($bookmark);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre lien a bien été enregistré.');

            return $bookmark->getFolder()
                ? $this->redirectToRoute('app_folder_show', ['id' => $bookmark->getFolder()->getId()])
                : $this->redirectToRoute('app_library_uncategorized');
        }

        return $this->render('library/bookmark_new.html.twig', $navigation + [
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/dossiers/nouveau', name: 'app_folder_new', methods: ['GET', 'POST'])]
    public function newFolder(Request $request): Response
    {
        $currentFolder = $this->folderFromQuery($request, 'parent');
        $navigation = $this->navigation('form', $currentFolder);
        $folder = (new Folder())->setParent($currentFolder);
        $form = $this->createForm(FolderType::class, $folder, [
            'folder_choices' => $navigation['folder_choices'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($folder);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre dossier a bien été créé.');

            return $this->redirectToRoute('app_folder_show', ['id' => $folder->getId()]);
        }

        return $this->render('library/folder_new.html.twig', $navigation + [
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function renderLibrary(string $view, ?Folder $folder = null): Response
    {
        $navigation = $this->navigation($view, $folder);
        $children = 'uncategorized' === $view
            ? []
            : ($navigation['folders_by_parent'][$folder?->getId() ?? 0] ?? []);

        return $this->render('library/index.html.twig', $navigation + [
            'children' => $children,
            'bookmarks' => $this->bookmarks->findForLibrary($folder, 'uncategorized' === $view),
        ]);
    }

    /**
     * @return array{view: string, current_folder: ?Folder, folders_by_parent: array<int, list<Folder>>, folder_choices: list<Folder>, counts: array{total: int, uncategorized: int, folders: array<int, int>}}
     */
    private function navigation(string $view, ?Folder $currentFolder): array
    {
        $byParent = [];

        foreach ($this->folders->findForNavigation() as $folder) {
            $byParent[$folder->getParent()?->getId() ?? 0][] = $folder;
        }

        $ordered = [];
        $appendChildren = function (int $parentId) use (&$appendChildren, &$ordered, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $folder) {
                $ordered[] = $folder;
                $appendChildren($folder->getId());
            }
        };
        $appendChildren(0);

        return [
            'view' => $view,
            'current_folder' => $currentFolder,
            'folders_by_parent' => $byParent,
            'folder_choices' => $ordered,
            'counts' => $this->bookmarks->countsByFolder(),
        ];
    }

    private function folderFromQuery(Request $request, string $key): ?Folder
    {
        $id = $request->query->get($key);

        if (null === $id || '' === $id) {
            return null;
        }

        if (!ctype_digit($id) || !($folder = $this->folders->find((int) $id))) {
            throw $this->createNotFoundException('Ce dossier n\'existe pas.');
        }

        return $folder;
    }
}
