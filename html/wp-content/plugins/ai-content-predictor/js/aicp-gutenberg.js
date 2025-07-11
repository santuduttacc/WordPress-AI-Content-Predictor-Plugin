jQuery(document).ready(function($) {
    const { select, subscribe, dispatch } = wp.data;
    const { getEditedPostContent } = select('core/editor');
    const { createNotice } = wp.data.dispatch('core/notices');
    const { removeAllNotices } = wp.data.dispatch('core/notices');

    const resultDiv = $('#aicp_result');
    
    function disablePublishBtn () {
        $('.editor-post-publish-button__button').prop('disabled', true);
    }
    function enablePublishBtn () {
        $('.editor-post-publish-button__button').prop('disabled', false);
    }
    function isPostSavingLocked() {
        return select('core/editor').isPostSavingLocked();
    }
    function lockPostSaving(message = null, messageType = 'info') {
        $('.editor-post-publish-button__button').addClass('lockPostSaving');
        dispatch('core/editor').lockPostSaving();

        if (message) {
            removeAllNotices();
            createNotice(messageType, message);
            resultDiv.text(message);
        }
    }
    function unlockPostSaving(source = 'unknown', message = null, messageType = 'info') {
        $('.editor-post-publish-button__button').removeClass('lockPostSaving');
        //dispatch('core/editor').unlockPostSaving();
        if( source == 'save_click' )
        {
            // const postId = getPostIdFromUrl();
            // if( !postId ) {
            //     // Publish this only in add mode
            //     dispatch('core/editor').editPost({ status: 'publish' });
            // }
            dispatch('core/editor').editPost({ status: 'publish' });
            dispatch('core/editor').savePost();
        }
        
        if (message) {
            removeAllNotices();
            createNotice(messageType, message);
            resultDiv.text(message);
        }
    }
    function analyzeContent(source = 'unknown') {

        const content = getEditedPostContent();
        //const postContent = content.replace(/<\/?[^>]+(>|$)/g, ""); // Strip HTML tags
        const postContent = content.replace(/<\/?[^>]+(>|$)|\r|\n/g, ""); // Strip HTML tags and line breaks

        console.log(postContent+' - analyzeContent Loaded from Gutenberg');
        // If content is not available, exit the function
        if (postContent.trim() === '') {
            lockPostSaving('Please write your content and click the publish button to verify and publish it');
            return;
        }

        lockPostSaving('Please hold on. We are analyzing the content');

        var analyzeResult;
        const aiProgressDiv = $('#aicp_progress_ai');
        const humanProgressDiv = $('#aicp_progress_human');
        const mixedProgressDiv = $('#aicp_progress_mixed');

        aiProgressDiv.text('');
        humanProgressDiv.text('');
        mixedProgressDiv.text('');
        
        $.ajax({
            url: aicpData.ajaxUrl,
            method: 'POST',
            data: {
                action: 'aicp_analyze_content',
                content: postContent
            },
            success: function(response) {
                const probabilities = response.documents[0].class_probabilities;

                const aiPercentage = (probabilities.ai * 100).toFixed(2);
                const humanPercentage = (probabilities.human * 100).toFixed(2);
                const mixedPercentage = (probabilities.mixed * 100).toFixed(2);

                //console.log(response);
                console.log('aiPercentage-'+aiPercentage+',humanPercentage-'+humanPercentage+',mixedPercentage-'+mixedPercentage);

                aiProgressDiv.width(`${aiPercentage}%`);
                humanProgressDiv.width(`${humanPercentage}%`);
                mixedProgressDiv.width(`${mixedPercentage}%`);

                if (aiPercentage > 20) {
                    aiProgressDiv.text(`${aiPercentage}%`);
                    analyzeResult = `${aiPercentage}% of this content is likely AI-generated. Publishing is disabled.`;
                    
                    lockPostSaving(analyzeResult, 'error');

                } else {
                    humanProgressDiv.text(`${humanPercentage}%`);
                    analyzeResult = `This content is human-generated.`;
                    
                    unlockPostSaving(source, analyzeResult, 'success');
               
                }
                // else {
                //     mixedProgressDiv.text(`${mixedPercentage}%`);
                //     analyzeResult = `This content is a mix of AI and human.`;
                    
                //     unlockPostSaving(source, analyzeResult, 'success');
                // }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                let errorMessage = 'Error analyzing content.';
                if (XMLHttpRequest.responseJSON && XMLHttpRequest.responseJSON.error) {
                    errorMessage = XMLHttpRequest.responseJSON.error;
                }
                
                lockPostSaving(errorMessage, 'error');
            }
        });
    }
    function checkContentLoaded() {
        // Function is used only in edit mode 
        const content = getEditedPostContent();
        if (content.trim() !== '') {
            analyzeContent('window_load');
        } else {
            console.log('retry by setTimeout');
            setTimeout(checkContentLoaded, 500); //  If content is empty, try again after 500ms
        }
    }
    function getPostIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('post');
    }
    $(window).on('load', function() {
        lockPostSaving(); // lock the post saving behaviour Gutenberg

        const postId = getPostIdFromUrl();
        if (postId) {
            checkContentLoaded();
        } else {
            // Add new post
            analyzeContent('window_load');
        }
    });
    $(document).on('click', '.editor-post-publish-button__button', function(event) {
        event.preventDefault();
        analyzeContent('save_click');
    });
});