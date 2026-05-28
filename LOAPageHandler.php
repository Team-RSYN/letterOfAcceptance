<?php

namespace APP\plugins\generic\letterOfAcceptance;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\plugins\generic\letterOfAcceptance\classes\Constants;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LOAPageHandler extends Handler {

    /** @var null|Publication $publication being requested */
    public ?Publication $publication = null;

    public ?Submission $submission = null;

    public function __construct(public LetterOfAcceptancePlugin $plugin)
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['get']
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function get($args, PKPRequest $request)
    {
        $submissionId = $args[0] ?? null;
        $this->submission = Repo::submission()->get((int) $submissionId);
        if (!$this->submission) {
            throw new NotFoundHttpException();
        }
        $this->publication = $this->submission->getCurrentPublication();
        if (!$this->publication) {
            throw new NotFoundHttpException();
        }
        $primaryAuthor = $this->publication->getPrimaryAuthor();
        $affiliation = $primaryAuthor
            ? $primaryAuthor->getLocalizedAffiliationNamesAsString()
            : '';
        $site = $request->getSite();
        $journal = $request->getContext();

        if (!$journal) {
            throw new NotFoundHttpException();
        }

        // Create letter
        // First get template
        $template = $this->plugin->getSetting($journal->getId(), Constants::SETTING_TEMPLATE)
            ?: $this->plugin->getSetting(null, Constants::SETTING_TEMPLATE);

        $journalLogo = '';
        $thumb = $journal->getLocalizedData('journalThumbnail');
        if ($thumb) {
            $journalFilesPath = $request->getBaseUrl() . '/' . Config::getVar('files', 'public_files_dir') . '/journals/';
            $uploadName = rawurlencode($thumb['uploadName']);
            $url = $journalFilesPath . $journal->getId() . '/' . $uploadName . '?v=' . sha1($thumb['dateUploaded']);
            $journalLogo = '<img style="max-width:200px;height:auto" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" />';
        }

        // Next Build up variables
        $templateVars = [
            'currentDate' => date('d M Y'),
            'authorFullName' => $primaryAuthor ? $primaryAuthor->getFullName() : 'Unknown',
            'authorAffiliation' => $affiliation,
            'submissionTitle' => $this->publication->getLocalizedFullTitle(),
            'submissionId' => $this->submission->getId(),
            'journalName' => $journal->getLocalizedName(),
            'siteName' => $site->getLocalizedTitle(),
            'journalPrincipalContactName' => $journal->getContactName(),
            'journalPrincipalContactEmail' => $journal->getContactEmail(),
            'journalLogo' => $journalLogo,
        ];

        // Replace variables (For some reason PKP does this in Mail, but it's fine to use)
        $template = Mail::compileParams($template, $templateVars);

        if ($request->getUserVar('html')) {
            echo $template;
        } else {
            // Use MPDF bundled with PKPLib to export a PDF
            $mpdf = new \Mpdf\Mpdf();
            $mpdf->WriteHTML($template);
            $mpdf->Output('LetterOfAcceptance-' . $submissionId . '.pdf', \Mpdf\Output\Destination::INLINE);
        }

        exit;
    }

}
