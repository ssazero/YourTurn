<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\FlatFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Flat;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use App\Form\RegistrationFormType;

class FlatsServiceController extends AbstractController {
    /**
     * @Route("/flats/list", name="flats_service")
     * @param PaginatorInterface $paginator
     * @param Request $request
     * @param UserInterface $user
     * @return Response
     */
    public function index(PaginatorInterface $paginator, Request $request, UserInterface $user) {
        $repository = $this->getDoctrine()->getRepository(Flat::class);
        $em = $this->getDoctrine()->getManager();

        $msg = null;
        if (!empty($request->get('msg')))
            $msg = $request->get('msg');

        if ($request->query->has('option') && !empty($request->query->get('value'))) {
            $value = $request->query->get('value');
            $option = $request->query->get('option');

            $flats = $repository->findBy([
                $option => $value
            ]);
        } else {
            $flats = $repository->findAll();
        }

        $numberOfUsers = [];
        $alreadyIn = [];

        foreach ($flats as $key => $flat) {
            $q = $em->createQuery("SELECT count(u.id) FROM App:User u WHERE ?1 MEMBER OF u.flats")->setParameter(1, $flats[$key]->getId());
            $numberOfUsers [$key] = $q->getSingleScalarResult();

            $alreadyIn [$key] = false;

            // check if the association exists
            foreach ($user->getFlats() as $each) {
                if ($each->getId() == $flat->getId()) {
                    $alreadyIn [$key] = true;
                }
            }
        }

        return $this->render('flats_service/list.html.twig', [
            'flats' => $paginator->paginate(
                $flats,
                $request->query->getInt('page', 1),
                10
            ),
            'numberOfUsers' => $numberOfUsers,
            'alreadyIn' => $alreadyIn,
            'msg' => $msg
        ]);
    }

    /**
     * @Route("/flats/create", name="create_flat")
     * @param Request $request
     * @return Response
     */
    public function create(Request $request) {
        $flat = new Flat();
        $form = $this->createForm(FlatFormType::class, $flat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();

            $flat->setName($data->getName());
            $flat->setPassword($data->getPassword());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($flat);
            $entityManager->flush();

            return $this->redirectToRoute('flats_service');
        }

        return $this->render('flats_service/create.html.twig', [
            'flatForm' => $form->createView()
        ]);
    } //INSERT INTO `users_flats` (`user_id`, `flat_id`) VALUES ('6', '1');

    /**
     * @Route("/flats/join", name="join_flat")
     * @param Request $request
     * @param UserInterface $user
     * @return Response
     */
    public function join(Request $request, UserInterface $user)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $flatId = $request->query->get('0');
        $pw = $request->get('password');

        $flat = $this->getDoctrine()
            ->getRepository(Flat::class)
            ->find($flatId);

        if ($pw != $flat->getPassword()){
            return $this->redirectToRoute('flats_service', [
                'msg' => "wrong password"
            ]);
        }

        $user->addFlat($flat);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('flats_service');
    }

    /**
     * @Route("/flats/leave", name="leave_flat")
     * @param Request $request
     * @param UserInterface $user
     * @return Response
     */
    public function leave(Request $request, UserInterface $user)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $flatId = $request->query->get('0');

        $flat = $this->getDoctrine()
            ->getRepository(Flat::class)
            ->find($flatId);

        $user->removeFlat($flat);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('flats_service');
    }
}