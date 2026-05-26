<?php

/**
 * Main class for letter of acceptance plugin
 * 
 * @author Joe Simpson
 * 
 * @class LetterOfAcceptancePlugin
 *
 * @brief LetterOfAcceptancePlugin
 */

namespace APP\plugins\generic\letterOfAcceptance;

use APP\core\Request;
use APP\core\Application;
use APP\plugins\generic\letterOfAcceptance\classes\Settings\Actions;
use APP\plugins\generic\letterOfAcceptance\classes\Settings\Manage;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;

class LetterOfAcceptancePlugin extends GenericPlugin {

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled()) {
            
            Hook::add('LoadHandler', [$this, 'setPageHandler']);

            $request = Application::get()->getRequest();
            $templateMgr = TemplateManager::getManager($request);
            $this->addJavaScript($request, $templateMgr);

        }

        return $success;
    }

    /**
     * Route requests for the `example` page to a custom page handler
     */
    public function setPageHandler(string $hookName, array $args): bool
    {
        $page =& $args[0];
        $handler =& $args[3];
        if ($this->getEnabled() && $page === 'loa') {
            $handler = new LOAPageHandler($this);
            return true;
        }
        return false;
    }

    public function addJavaScript($request, $templateMgr)
    {
        /** @var PageRouter */
        $router = $request->getRouter();
        $handler = $router->getHandler();
        if (!$handler) {
            return;
        }
        $userRoles = (array) $handler->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {

            $templateMgr->addJavaScript(
                'LetterOfAcceptanceButton',
                "{$request->getBaseUrl()}/{$this->getPluginPath()}/admin.js",
                [
                    'inline' => false,
                    'contexts' => ['backend'],
                    'priority' => TemplateManager::STYLE_SEQUENCE_LAST
                ]
            );

        }
    }

    /**
     * Provide a name for this plugin
     *
     * The name will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDisplayName()
    {
        return __('plugins.generic.letterOfAcceptance.displayName');
    }

    /**
     * Provide a description for this plugin
     *
     * The description will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDescription()
    {
        return __('plugins.generic.letterOfAcceptance.description');
    }

    /**
     * Add a settings action to the plugin's entry in the plugins list.
     *
     * @param Request $request
     * @param array $actionArgs
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    /**
     * Load a form when the `settings` button is clicked and
     * save the form when the user saves it.
     *
     * @param array $args
     * @param Request $request
     */
    public function manage($args, $request): JSONMessage
    {
        $manage = new Manage($this);
        return $manage->execute($args, $request);
    }

    /**
     * This plugin can be used site-wide or in a specific context. The
     * isSitePlugin check is used to grant access to different users, so this
     * plugin must return true only if the user is currently in the site-wide
     * context.
     *
     * @see PluginGridRow::_canEdit()
     *
     */
    public function isSitePlugin(): bool
    {
        return !Application::get()->getRequest()->getContext();
    }

}
