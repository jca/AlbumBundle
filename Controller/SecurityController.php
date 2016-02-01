<?php

namespace Jcc\Bundle\AlbumBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\SecurityContext;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 * Controller for security actions - login, logout, etc
 *
 * TODO: Make this security optional
 */
class SecurityController extends Controller
{
    /**
     * Show login form
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @Template()
     * @Route("/login", name="_security_login")
     */
    public function loginAction()
    {
        if ($this->get('request')->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $this->get('request')->attributes->get(SecurityContext::AUTHENTICATION_ERROR);
        } else {
            $error = $this->get('request')->getSession()->get(SecurityContext::AUTHENTICATION_ERROR);
        }

        return array(
            'last_username' => $this->get('request')->getSession()->get(SecurityContext::LAST_USERNAME),
            'error'         => $error,
        );
    }

    /**
     * @Route("/manager/login_check", name="_security_check")
     */
    public function securityCheckAction()
    {
        // The security layer will intercept this request
        return null;
    }

    /**
     * Stub for logout action - actually handled by framework listener
     *
     * @throws \RuntimeException
     * @Route("/logout", name="_security_logout")
     */
    public function logoutAction()
    {
        return new Response('');
    }

    /**
     * Show access denied page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/accessdenied", name= "_security_accessdenied")
     */
    public function accessDeniedAction()
    {
        $this->get('request')->getSession()->set(SecurityContext::AUTHENTICATION_ERROR, "You don't have access to this page");

        return $this->redirect($this->generateUrl('_security_login'));
    }
}
