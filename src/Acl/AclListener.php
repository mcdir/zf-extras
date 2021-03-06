<?php

namespace ZfExtra\Acl;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\Permissions\Acl\Acl;
use Zend\ServiceManager\ServiceManager;
use Zend\Session\Container;
use Zend\View\Helper\Navigation;
use ZfExtra\Acl\Exception\PermissionDeniedException;
use ZfExtra\Acl\RoleResolver\RoleResolverInterface;
use ZfExtra\Mvc\Application;

/**
 *
 * @author Alex Oleshkevich <alex.oleshkevich@muehlemann-popp.ch>
 */
class AclListener extends AbstractListenerAggregate
{

    /**
     *
     * @var RoleResolverInterface
     */
    protected $roleResolver;

    /**
     * 
     * @param RoleResolverInterface $roleResolver
     */
    public function __construct(RoleResolverInterface $roleResolver)
    {
        $this->roleResolver = $roleResolver;
    }

    public function attach(EventManagerInterface $events, $priority = 1)
    {
        if (PHP_SAPI !== 'cli') {
            $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute']);
            $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRender']);
        }
    }

    /**
     * 
     * @param MvcEvent $event
     * @return boolean
     */
    public function onRoute(MvcEvent $event)
    {
        /* @var $acl Acl */
        $acl = $event->getApplication()->getServiceManager()->get('acl');
        /* @var $app Application */
        $app = $event->getTarget();
        $sm = $event->getApplication()->getServiceManager();
        $config = $sm->get('config.helper');
        $routeMatch = $event->getRouteMatch();
        $resource = $routeMatch->getParam('controller');
        $action = $routeMatch->getParam('action');
        
        if ($acl->hasResource($resource)) {
            if (!$acl->isAllowed($this->getRole($event), $resource, $action)) {
                $identity = $this->getIdentity($event);
                if (!$identity) {
                    $handler = $config->get('acl.violation_handlers.unauthenticated');
                    if (!$handler) {
                        return $this->triggerUnauthorizedError($app, $event);
                    } else {
                        $session = new Container('acl');
                        $session->redirectAfterLoginTo = $sm->get('request')->getServer('REQUEST_URI');
                        $event->getRouteMatch()->setParam('controller', $handler['controller']);
                        $event->getRouteMatch()->setParam('action', $handler['action']);
                        return false;
                    }
                    return false;
                } else {
                    $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
                    $event->setError('error-unauthorized-route');
                    $event->setParam('exception', new PermissionDeniedException('You are not authorized to access this page.'));
                    return $app->getEventManager()->triggerEvent($event);
                }
            }
        } else {
            $policy = $config->get('acl.policy', 'restrictive');
            switch ($policy) {
                case 'restrictive':
                default:
                    $handler = $config->get('acl.violation_handlers.unauthorized');
                    if (!$handler) {
                        return $this->triggerUnauthorizedError($app, $event);
                    } else {
                        $event->setParam('requested_resource', $resource);
                        $event->setParam('requested_action', $action);
                        $event->getRouteMatch()->setParam('controller', $handler['controller']);
                        $event->getRouteMatch()->setParam('action', $handler['action']);
                        return false;
                    }
                    break;
                case 'permissive':
                    // pass
                    break;
            }
        }
    }

    /**
     * 
     * @param MvcEvent $e
     */
    public function onRender(MvcEvent $e)
    {
        Navigation::setDefaultAcl($e->getApplication()->getServiceManager()->get('Acl'));
        Navigation::setDefaultRole($this->getRole($e));
    }

    /**
     * 
     * @param MvcEvent $e
     * @return object|null
     */
    protected function getIdentity(MvcEvent $e)
    {
        /* @var $sm ServiceManager */
        $sm = $e->getApplication()->getServiceManager();
        if (!$sm->has('Zend\Authentication\AuthenticationService')) {
            return null;
        }
        return $sm->get('Zend\Authentication\AuthenticationService')->getIdentity();
    }

    /**
     * 
     * @param MvcEvent $e
     * @return string
     */
    protected function getRole(MvcEvent $e)
    {
        $sm = $e->getApplication()->getServiceManager();
        $identity = $this->getIdentity($e);
        return $this->roleResolver->resolve($e, $identity);
    }

    /**
     * @todo handle with view strategy
     * @param Application $app
     * @param MvcEvent $event
     * @return void
     */
    protected function triggerUnauthorizedError(Application $app, MvcEvent $event)
    {
        $app->getServiceManager()->get('response')->setStatusCode(403);
        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError('error-unauthorized-route');
        $event->setParam('exception', new PermissionDeniedException('You are not authorized to access this page.', 403));
        return $app->getEventManager()->triggerEvent($event);
    }

}
