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
            // Guard against submissions that have not yet been published
            if (!publishedUrl) {
                return;
            }
            // Now we open a new window to display the submission LOA
            window.open( publishedUrl.replace('/article/view', '/loa/get') );
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