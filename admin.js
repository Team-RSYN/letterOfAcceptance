/**
 * This extension adds a button to the submission workflow to open the
 * submission acceptance letter URL
 */


pkp.registry.storeExtend("workflow", (piniaContext) => {

    const workflowStore = piniaContext.store;
    workflowStore.extender.extendFn('getHeaderItems', (items, args) => {

        workflowStore['letterOfAcceptance'] = function(){
            // Get the submission directly from the store, as we don't have access to
            // wrapped arguments by adding the action after-OJS code
            let publishedUrl = workflowStore['submission']['urlPublished'];
            let submissionId = workflowStore['submission']['id'];
            // Guard against submissions that have not yet been published or missing data
            if (!publishedUrl || !submissionId) {
                return;
            }
            // Build the LOA URL from the base URL of the published article URL.
            // Extract everything up to and including the journal path prefix by
            // splitting on /article/view and taking the part before it.
            let articleViewIndex = publishedUrl.indexOf('/article/view');
            if (articleViewIndex === -1) {
                return;
            }
            let baseUrl = publishedUrl.substring(0, articleViewIndex);
            // Now we open a new window to display the submission LOA
            window.open(baseUrl + '/loa/get/' + encodeURIComponent(submissionId));
        }

        items.push({
            component: 'WorkflowActionButton',
            props: {
                label: 'Letter of Acceptance',
                action: 'letterOfAcceptance',
            }
        })
        return items;

    });

});