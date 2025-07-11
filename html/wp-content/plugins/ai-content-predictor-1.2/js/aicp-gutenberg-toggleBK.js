jQuery(document).ready(function($) {
    const { select, subscribe, dispatch } = wp.data;

    subscribe(function() {
        const isReady = select('core/editor').isCleanNewPost() || select('core/block-editor').getBlockCount() > 0;
        if (isReady) {
            //console.log('subscribe called from new');
            // Ensure this block runs only once
            if ($('.toggle_aicp_side_panel').length > 0) {
                return; // Button already exists, no need to add it again
            }

            const publishButton = $('.editor-post-publish-button__button');
            if (publishButton.length > 0) {
                // Create a new button element
                const toggleButton = $('<button>', {
                    type : 'button',
                    class: 'components-button is-secondary toggle_aicp_side_panel',
                    text: 'WP Writer',
                    click: function() {
                        // Toggle the visibility of the side panel
                        $('#aicp_side_panel').toggleClass('is-visible');
                    }
                });
    
                // Insert the new button immediately after the publish button
                publishButton.after(toggleButton);
    
                // Create the side panel structure
                const sidePanel = $('<div>', {
                    id: 'aicp_side_panel',
                    class: 'aicp_side_panel',
                    html: `<div class="aicp_panel_header">
                                <h3>WP Writer</h3>
                                <button class="aicp_close_button">&times;</button>
                           </div>
                           <div id="aicp_progress_bar">
                               <div id="aicp_progress_ai" class="aicp_progress"></div>
                               <div id="aicp_progress_human" class="aicp_progress"></div>
                               <div id="aicp_progress_mixed" class="aicp_progress"></div>
                           </div>
                           <div id="aicp_result"></div>`
                });
    
                // Append the side panel to the body or any parent container
                $('body').append(sidePanel);
    
                // Close button inside the panel
                $('.aicp_close_button').click(function() {
                    $('#aicp_side_panel').removeClass('is-visible');
                });
            } else {
                console.error('Publish button not found.');
            }
        }
    });

    let hasAddedContent = false;
    const unsubscribe = subscribe(() => {
        
        hasAddedContent = true;
        unsubscribe();
    
    });

    $(document).on("click", 'button.components-button[aria-label="WP Writer"]', function(event) {
            alert('clicked');
    });
    
    $(window).on('load', function() {$('button.components-button[aria-label="WP Writer"]').click();
        // Wait until the sidebar icon is available in the DOM
        setTimeout(function() {
            // Simulate a click on the pen icon to open the sidebar
            
        }, 100); // Delay to ensure the button is rendered
    });

    // MutationObserver to detect when the sidebar is fully rendered
    const observer = new MutationObserver(function(mutations, observerInstance) {
        const aiProgressDiv = $('#aicp_progress_ai');

        if (aiProgressDiv.length > 0) {
            // Stop observing once the element is found
            observerInstance.disconnect();

            alert('Element found!'); // Now it works after elements are rendered

            // Initialize your scripts or functions here
            // For example, add the click event handler for tabs
            $(document).on("click", "#aicp_tabs a", function(event) {
                event.preventDefault();
                $(".tab-active").removeClass("tab-active");
                $(this).parent().addClass("tab-active");
                $(".tabs-stage .aicp_container").hide();
                $($(this).attr("href")).show();
            });

            // Trigger the first tab to show by default
            $("#aicp_tabs a:first").trigger("click");
        }
    });

    // Start observing the document body for changes in the child elements (subtree)
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // let aiProgressDiv, humanProgressDiv, mixedProgressDiv, resultDiv;
    const publishButton = $('.editor-post-publish-button__button');
    const resultDiv = $('#aicp_result');

   // function initializeElements() {
   //     aiProgressDiv = $('#aicp_progress_ai');
   //     humanProgressDiv = $('#aicp_progress_human');
   //     mixedProgressDiv = $('#aicp_progress_mixed');
   //     resultDiv = $('#aicp_result');
   // }

   // function checkForElements() {
    //     if ($('#aicp_progress_ai').length > 0) {
    //         alert('Element found!'); // This will now work after elements are rendered
    //         // Initialize your scripts or functions here
    //     } else {
    //         setTimeout(checkForElements, 100); // Check again after 100ms
    //     }
    // }

    // alert($('#aicp_progress_ai').length);
    // if ( $('#aicp_progress_ai').length > 0 ) {
    //     alert('ok'); // Button already exists, no need to add it again
    // }


    // setTimeout(temp, 2500);
    // function temp() {
    //     if ( $('#aicp_progress_ai').length > 0 ) {
    //         alert('ok'); // Button already exists, no need to add it again
    //     }
    // }
    
    

    // setTimeout(initializeElements, 500); // Delay to ensure elements are in DOM

    
    //const resultDiv = $('#aicp_result');
    //const publishButton = $('.editor-post-publish-button__button');

    //resultDiv.text('qdqdqq');
    //     humanProgressDiv.text('qddqq');
    //     mixedProgressDiv.text('ddqd');

});


            