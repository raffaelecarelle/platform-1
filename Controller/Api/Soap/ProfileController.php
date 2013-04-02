<?php

namespace Oro\Bundle\UserBundle\Controller\Api\Soap;

use BeSimple\SoapBundle\ServiceDefinition\Annotation as Soap;

use Oro\Bundle\UserBundle\Entity\UserSoap;

class ProfileController extends BaseController
{
    /**
     * @Soap\Method("getUsers")
     * @Soap\Param("page", phpType = "int")
     * @Soap\Param("limit", phpType = "int")
     * @Soap\Result(phpType = "Oro\Bundle\UserBundle\Entity\UserSoap[]")
     */
    public function cgetAction($page = 1, $limit = 10)
    {
        return $this->container->get('knp_paginator')->paginate(
            $this->getUserManager()->getListQuery(),
            (int) $page,
            (int) $limit
        );
    }

    /**
     * @Soap\Method("getUser")
     * @Soap\Param("id", phpType = "int")
     * @Soap\Result(phpType = "Oro\Bundle\UserBundle\Entity\UserSoap")
     */
    public function getAction($id)
    {
        return $this->getEntity('OroUserBundle:User', $id);
    }

    /**
     * @Soap\Method("createUser")
     * @Soap\Param("profile", phpType = "\Oro\Bundle\UserBundle\Entity\UserSoap")
     * @Soap\Result(phpType = "boolean")
     */
    public function createAction($profile)
    {
        $entity = $this->getUserManager()->createFlexible();
        $form   = $this->container->get('oro_user.form.profile.api')->getName();

        $this->container->get('oro_soap.request')->fix($form);

        return $this->processForm($form, $entity);
    }

    /**
     * @Soap\Method("updateUser")
     * @Soap\Param("id", phpType = "int")
     * @Soap\Param("profile", phpType = "\Oro\Bundle\UserBundle\Entity\UserSoap")
     * @Soap\Result(phpType = "boolean")
     */
    public function updateAction($id, $profile)
    {
        $entity = $this->getEntity('OroUserBundle:User', $id);
        $form   = $this->container->get('oro_user.form.profile.api')->getName();

        $this->container->get('oro_soap.request')->fix($form);

        return $this->processForm($form, $entity);

    }

    /**
     * @Soap\Method("deleteUser")
     * @Soap\Param("id", phpType = "int")
     * @Soap\Result(phpType = "boolean")
     */
    public function deleteAction($id)
    {
        $entity = $this->getEntity('OroUserBundle:User', $id);

        $this->getUserManager()->deleteUser($entity);

        return true;
    }

    /**
     * @Soap\Method("getUserRoles")
     * @Soap\Param("id", phpType = "int")
     * @Soap\Result(phpType = "Oro\Bundle\UserBundle\Entity\Role[]")
     */
    public function getRolesAction($id)
    {
        return $this->getEntity('OroUserBundle:User', $id)->getRoles();
    }

    /**
     * @Soap\Method("getUserGroups")
     * @Soap\Param("id", phpType = "int")
     * @Soap\Result(phpType = "Oro\Bundle\UserBundle\Entity\Group[]")
     */
    public function getGroupsAction($id)
    {
        return $this->getEntity('OroUserBundle:User', $id)->getGroups();
    }

    /**
     * @Soap\Method("getUserBy")
     * @Soap\Param("filters", phpType = "BeSimple\SoapCommon\Type\KeyValue\String[]")
     * @Soap\Result(phpType = "Oro\Bundle\UserBundle\Entity\UserSoap")
     */
    public function getByAction(array $filters)
    {
        if (empty($filters)) {
            throw new \SoapFault('NOT_FOUND', 'Empty filter data');
        }

        $entity = $this->getUserManager()->findUserBy($filters);

        if (!$entity) {
            throw new \SoapFault('NOT_FOUND', 'User can not be found using specified filter');
        }

        return $entity;
    }

    /**
     * @Soap\Method("getUserAcl")
     * @Soap\Param("id", phpType = "int")
     * @Soap\Result(phpType = "string[]")
     */
    public function getAclAction($id)
    {
        $user = ($this->getUserManager()->findUserBy(array('id' => (int) $id)));

        if (!$user) {
            throw new \SoapFault('NOT_FOUND', 'User can not be found using specified filter');
        }

        return $this->getAclManager()->getAclForUser($user);
    }

    /**
     * @return \Oro\Bundle\UserBundle\Acl\Manager
     */
    protected function getAclManager()
    {
        return $this->container->get('oro_user.acl_manager');
    }

    /**
     * @return \Oro\Bundle\UserBundle\Entity\UserManager
     */
    protected function getUserManager()
    {
        return $this->container->get('oro_user.manager');
    }
}
