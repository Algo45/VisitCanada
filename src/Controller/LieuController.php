<?php

namespace App\Controller;

use App\Entity\Lieu;
use App\Entity\Photo;
use App\Form\LieuType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class LieuController extends AbstractController
{
    #[Route('/lieu/new', name: 'lieu_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $lieu = new Lieu();
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Enregistrement du lieu
            $entityManager->persist($lieu);

            // Gestion des photos téléchargées
            $photos = $form->get('photos')->getData(); // Assuming 'photos' is the field for multiple files

            foreach ($photos as $photoFile) {
                $photo = new Photo();
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid('',true) . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('uploads_directory'), // Make sure this parameter is defined in config/services.yaml
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gestion de l'exception en cas d'erreur lors du téléchargement
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload des photos.');
                    return $this->redirectToRoute('lieu_new');
                }

                $photo->setPath($newFilename);
                $photo->setLieu($lieu);
                $entityManager->persist($photo);
            }

            $entityManager->flush();

            // Redirection vers la page de liste après l'ajout
            return $this->redirectToRoute('lieu_list');
        }

        return $this->render('lieu/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/lieu', name: 'lieu_list')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $lieux = $entityManager->getRepository(Lieu::class)->findAll();

        return $this->render('lieu/index.html.twig', [
            'lieux' => $lieux,
        ]);
    }

    #[Route('/lieu/{id}', name: 'lieu_show', requirements: ['id' => '\d+'])]
    public function show(Lieu $lieu): Response
    {
        return $this->render('lieu/show.html.twig', [
            'lieu' => $lieu,
            'photos' => $lieu->getPhotos(),
        ]);
    }
}
