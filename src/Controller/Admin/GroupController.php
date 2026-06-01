<?php declare(strict_types=1);

namespace Group\Controller\Admin;

use Common\Stdlib\PsrMessage;
use Group\Form\GroupForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;

class GroupController extends AbstractActionController
{
    // TODO Search action.
    // public function searchAction()
    // {
    // }

    public function browseAction()
    {
        $this->setBrowseDefaults('name', 'asc');
        $response = $this->api()->search('groups', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $groups = $response->getContent();
        $groupCount = $this->viewHelpers()->get('groupCount');
        $groupCount = $groupCount($groups);

        return new ViewModel([
            'groups' => $groups,
            'groupCount' => $groupCount,
        ]);
    }

    public function showAction()
    {
        $response = $this->apiReadFromIdOrName();

        $entity = $response->getContent();
        return new ViewModel([
            'group' => $entity,
            'resource' => $entity,
        ]);
    }

    public function showDetailsAction()
    {
        $response = $this->apiReadFromIdOrName();
        $group = $response->getContent();

        $groupCount = $this->viewHelpers()->get('groupCount');
        $groupCount = $groupCount($group);
        $groupCount = reset($groupCount);

        $view = new ViewModel([
            'resource' => $group,
            'groupCount' => $groupCount,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $entity = $this->apiReadFromIdOrName()->getContent();
                $response = $this->api($form)->delete('groups', $entity->id());
                if ($response) {
                    $this->messenger()->addSuccess('Group successfully deleted.'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/group');
    }

    public function deleteConfirmAction()
    {
        $response = $this->apiReadFromIdOrName();
        $group = $response->getContent();

        $groupCount = $this->viewHelpers()->get('groupCount');
        $groupCount = $groupCount($group);
        $groupCount = reset($groupCount);

        $view = new ViewModel([
            'group' => $group,
            'groupCount' => $groupCount,
            'resource' => $group,
            'resourceLabel' => 'group',
            'partialPath' => 'group/admin/group/show-details',
        ]);
        return $view
            ->setTemplate('common/delete-confirm-details')
            ->setTerminal(true);
    }

    public function batchDeleteConfirmAction()
    {
        /** @var \Omeka\Form\ConfirmForm $form */
        $form = $this->getForm(ConfirmForm::class);
        $routeAction = $this->params()->fromQuery('all') ? 'batch-delete-all' : 'batch-delete';
        $form
            ->setAttribute('action', $this->url()->fromRoute(null, ['action' => $routeAction], true))
            ->setAttribute('id', 'batch-delete-confirm')
            ->setAttribute('class', $routeAction)
            ->setButtonLabel('Confirm delete'); // @translate
        $view = new ViewModel([
            'form' => $form,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one group to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        /** @var \Omeka\Form\ConfirmForm $form */
        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('groups', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Groups successfully deleted.'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction(): void
    {
        // TODO Support batch delete all.
        $this->messenger()->addError('Delete of all groups is not supported currently.'); // @translate
    }

    public function addAction()
    {
        /** @var \Group\Form\GroupForm $form */
        $form = $this->getForm(GroupForm::class);
        $form
            ->setAttribute('action', $this->url()->fromRoute(null, [], true))
            ->setAttribute('enctype', 'multipart/form-data')
            ->setAttribute('id', 'add-group');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $response = $this->api($form)->create('groups', $data);
                if ($response) {
                    $message = new PsrMessage(
                        'Group successfully created. {link}Add another group?{link_end}', // @translate
                        [
                            'link' => sprintf('<a href="%s">', htmlspecialchars($this->url()->fromRoute(null, [], true))),
                            'link_end' => '</a>',
                        ]
                    );
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'form' => $form,
        ]);
    }

    public function updateAction()
    {
        $response = $this->apiReadFromIdOrName();
        $group = $response->getContent();
        $id = $group->id();
        $name = $this->params()->fromPost('text');

        $response = $this->api()->update('groups', $id, ['o:name' => $name], [], ['isPartial' => true]);
        if (!$response) {
            return $this->jSend()->fail(null, $this->translate('This group is invalid: it is a duplicate or it contains forbidden characters.')); // @translate
        }

        $group = $response->getContent();
        $escape = $this->viewHelpers()->get('escapeHtml');
        return $this->jSend()->success([
            'text' => $group->name(),
            'escaped' => $escape($group->name()),
            'urls' => [
                'update' => $group->url('update'),
                'show_details' => $group->url('show-details'),
                'delete_confirm' => $group->url('delete-confirm'),
                'users' => $group->urlEntities('user'),
                'item_sets' => $group->urlEntities('item-set'),
                'items' => $group->urlEntities('item'),
                'media' => $group->urlEntities('media'),
            ],
        ]);
    }

    protected function apiReadFromIdOrName()
    {
        $id = $this->params('id');
        if ($id) {
            $response = $this->api()->read('groups', $id);
        } else {
            $name = $this->params('name');
            $response = $this->api()->search('groups', [
                'name' => $this->params('name'),
                'limit' => 1,
            ]);
            $content = $response->getContent();
            if (empty($content)) {
                throw new \Omeka\Api\Exception\NotFoundException((string) (new PsrMessage(
                    '{entity} entity with criteria {"name":"{group_name}"} not found.', // @translate
                    ['entity' => 'Group\Entity\Group', 'group_name' => $name]
                ))->setTranslator($this->translator()));
            }
            $content = is_array($content) && count($content) ? $content[0] : null;
            $response->setContent($content);
        }
        return $response;
    }
}
