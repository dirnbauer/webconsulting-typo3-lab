<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Webconsulting\Flue\Domain\Repository\FlowRepository;
use Webconsulting\Flue\Service\EnvironmentGuard;
use Webconsulting\Flue\Service\FlowTriggerService;
use Webconsulting\Flue\Service\RunStore;
use Webconsulting\Flue\Support\Typed;

/**
 * Backend module "Flue": list flows, trigger a flow on a page (live via SSE, or
 * a synchronous no-JS fallback), and inspect durable run reports.
 */
#[AsController]
final class FlueModuleController extends ActionController
{
    private const CSS = 'EXT:flue/Resources/Public/Css/flue-backend.css';
    private const JS = '@webconsulting/flue/flue-run.js';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly PageRenderer $pageRenderer,
        private readonly BackendUriBuilder $backendUriBuilder,
        private readonly FlowRepository $flowRepository,
        private readonly RunStore $runStore,
        private readonly FlowTriggerService $flowTriggerService,
        private readonly EnvironmentGuard $environmentGuard,
    ) {
    }

    public function listAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile(self::CSS);
        $this->pageRenderer->loadJavaScriptModule(self::JS);

        $currentPageUid = Typed::int($this->request->getQueryParams()['id'] ?? 0);
        $runs = $this->runStore->recent(25);
        foreach ($runs as &$run) {
            $run['showUri'] = $this->uriBuilder->reset()->uriFor('show', ['run' => Typed::int($run['uid'])]);
            $run['createdFormatted'] = date('Y-m-d H:i', Typed::int($run['crdate']));
        }
        unset($run);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Flue', 'AI flows');
        $moduleTemplate->assignMultiple([
            'flows' => $this->flowRepository->findAll(),
            'runs' => $runs,
            'currentPageUid' => $currentPageUid,
            'currentWorkspace' => (int)$this->getBackendUser()->workspace,
            'blockReason' => $this->environmentGuard->getBlockReason(),
            'runUri' => $this->uriBuilder->reset()->uriFor('run'),
            'triggerEndpoint' => (string)$this->backendUriBuilder->buildUriFromRoute('ajax_flue_trigger'),
            'streamEndpoint' => (string)$this->backendUriBuilder->buildUriFromRoute('ajax_flue_stream'),
        ]);

        return $moduleTemplate->renderResponse('Flue/List');
    }

    /**
     * No-JS fallback: trigger + drain synchronously, then show the run.
     */
    public function runAction(): ResponseInterface
    {
        // Read plain field names from the raw body so the same form works for the
        // no-JS fallback and the (plain-keyed) AJAX trigger endpoint.
        $body = Typed::stringKeyedArray($this->request->getParsedBody());
        $flowUid = Typed::int($body['flow'] ?? 0);
        $pageUid = Typed::int($body['page'] ?? 0);
        $instructions = Typed::string($body['instructions'] ?? '');

        if ($flowUid <= 0 || $pageUid <= 0) {
            $this->addFlashMessage('Select a flow and provide a page uid.', 'Missing input', ContextualFeedbackSeverity::WARNING);
            return $this->redirect('list');
        }

        try {
            $result = $this->flowTriggerService->trigger($flowUid, 'pages', $pageUid, (int)$this->getBackendUser()->workspace, $instructions, (int)$this->getBackendUser()->getUserId());
            $this->flowTriggerService->drainRun($result['runUid']);
            return $this->redirect('show', null, null, ['run' => $result['runUid']]);
        } catch (\Throwable $e) {
            $this->addFlashMessage($e->getMessage(), 'Flow not started', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('list');
        }
    }

    public function showAction(int $run = 0): ResponseInterface
    {
        $this->pageRenderer->addCssFile(self::CSS);
        $flueRun = $run > 0 ? $this->runStore->load($run) : null;
        if ($flueRun === null) {
            $this->addFlashMessage('Run ' . $run . ' not found.', 'Not found', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('list');
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Flue run', '#' . $flueRun->uid);
        $moduleTemplate->assignMultiple([
            'run' => $flueRun,
            'listUri' => $this->uriBuilder->reset()->uriFor('list'),
            'createdFormatted' => date('Y-m-d H:i:s', $flueRun->crdate),
        ]);

        return $moduleTemplate->renderResponse('Flue/Show');
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            throw new \RuntimeException('No backend user available', 1760001200);
        }
        return $backendUser;
    }
}
