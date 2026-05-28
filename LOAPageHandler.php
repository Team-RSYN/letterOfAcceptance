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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LOAPageHandler extends Handler {

    /** @var null|Publication $publication being requested */
    public ?Publication $publication = null;

    public ?Submission $submission = null;

    public function __construct(public LetterOfAcceptancePlugin $plugin)
    {
        parent::__construct();   
    }

    public function get($args, PKPRequest $request)
    {

        $submissionId = $args[0];
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
        
        // Create letter
        // First get template
        $template = $this->plugin->getSetting($request->getContext()->getId(), Constants::SETTING_TEMPLATE)
            ?: $this->plugin->getSetting(null, Constants::SETTING_TEMPLATE);

        $journalLogo = '';
        $thumb = $journal->getLocalizedData('journalThumbnail');
        if($thumb) {
            $journalFilesPath = $request->getBaseUrl() . '/' . Config::getVar('files', 'public_files_dir') . '/journals/';
            $url = $journalFilesPath . $journal->getId() . '/' . $thumb['uploadName'] . '?v=' . sha1($thumb['dateUploaded']);
            $journalLogo = '<img style="max-width:200px;height:auto" src="' . $url . '" />';
        }

        // Next Build up variables
        $args = [
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
        $template = Mail::compileParams($template, $args);

        if (!empty($_GET['html'])) {
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
