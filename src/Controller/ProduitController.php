<?php

namespace App\Controller;

use DateTime;
use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @Route("/admin")
 */
class ProduitController extends AbstractController
{

    /**
     *@Route("/voir-les-produits", name="show_produit", methods={"GET"})
     */
    public function showProduit(ProduitRepository $produitRepository): Response
    {
        return $this->render("admin/show_produit.html.twig", [
            'produits' =>$produitRepository->findBy(['deletedAt' => null]),
        ]);
    }

    /**
     * @Route("/creer-un-produit", name="create_produit", methods={"GET|POST"}) 
     */
    public function createProduit(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $produit = new Produit();

        $form = $this->createForm(ProduitType::class, $produit)->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $produit->setCreatedAt(new DateTime());
            $produit->setUpdatedAt(new DateTime());

            $photo = $form->get('photo')->getData();

            if($photo) {
                // Méthode créée par nous-même pour réutiliser cette partie de code.
               $this->handleFile($produit, $photo, $slugger); 
            } // end if($photo)

            $entityManager->persist($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Le nouveau produit est en ligne !');
            return $this->redirectToRoute('show_produit');
            
        } // end if($form)

        return $this->render('admin/form/form_produit.html.twig', [
            'form' => $form->createView()
        ]);
    }
    
    /**
     * @Route("/modifier-un-produit_{id}", name="update_produit", methods={"GET|POST"})
     */
    public function updateProduit(Produit $produit, EntityManagerInterface $entityManager, Request $request, SluggerInterface $slugger): Response
    {
        $originalPhoto = $produit->getPhoto();

        $form = $this->createForm(ProduitType::class, $produit, [
            'photo' => $originalPhoto
        ])->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $produit->setUpdatedAt(new DateTime());

            $photo = $form->get('photo')->getData();

            if($photo) {
                $this->handleFile($produit, $photo, $slugger);
            } else {
                $produit->setPhoto($originalPhoto);
            }

            $entityManager->persist($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Le produit a bien été modifié !');
            return $this->redirectToRoute('show_produit');
        }

        return $this->render('admin/form/form_produit.html.twig', [
            'form' => $form->createView(),
            'produit' => $produit,
        ]);
    }

    private function handleFile(Produit $produit, UploadedFile $photo, SluggerInterface $slugger): void
    {
        $extension = '.' . $photo->guessExtension();

        $safeFilename = $slugger->slug($produit->getTitle());

        $newFilename = $safeFilename . '_' . uniqid() . $extension;

        try{
                    $photo->move($this->getParameter('uploads_dir'), $newFilename);
                    $produit->setPhoto($newFilename);
                } catch (FileException $exception) {
                    $this->addFlash('warning', 'La photo du produit n\'a pas pu être importée, veuillez réessayer en modifiant le produit.');
                    //return $this->redirectToRoute('show_produit');
                } // end catch()
    }


    /**
     * @Route("/archiver-un-produit/{id}", name="soft_delete_produit", methods={"GET"})
     */
    public function softDeleteProduit(Produit $produit, EntityManagerInterface $entityManager): Response
    {

        // setDeletedAt() nous permet de créer une bascule (on/off) sur le produit pour afficher en ligne ou le mettre dans la poubelle
         # CEPENDANT ! En BDD la ligne existe toujours, l'objet Produit n'est pas supprimé.
        $produit->setDeletedAt(new DateTime());

        $entityManager->persist($produit);
        $entityManager->flush();

        $this->addFlash('success', "Le produit " . $produit->getTitle() ." a bien été archivé.");
        return $this->redirectToRoute('show_produit');        
    }

    /**
     * @Route("/restaurer-un-produit/{id}", name="restore_produit", methods={"GET"})
     */
    public function restoreProduit(Produit $produit, EntityManagerInterface $entityManager): Response
    {
        // Côté miroir de la bascule (on/off) qui permet de restaurer en ligne le produit.
        $produit->setDeletedAt(null);

        $entityManager->persist($produit);
        $entityManager->flush();

        $this->addFlash('success', "Le produit " . $produit->getTitle() ." a bien été restauré.");
        return $this->redirectToRoute('show_produit');  
    }

}