jQuery(document).ready(function($) {
    const { select, subscribe, dispatch } = wp.data;
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar } = wp.editor;
    const { createElement, RawHTML } = wp.element; // Use RawHTML for raw HTML

    const selectCoreEditor = select('core/editor');
    const dispatchCoreEditor = dispatch('core/editor');
    const dispatchCoreNotices = dispatch('core/notices');

    var myDoughnutChart;
    let aiResponse = ''; // Declare a global variable to store AJAX response
    const progressChart = `
        <div id="aicp_progress_chart">
            <canvas id="myDoughnutChart"></canvas>
        </div>`;

    const progressBar = `
        <div id="aicp_progress_bar">
            <div id="aicp_progress_ai" class="aicp_progress"></div>
            <div id="aicp_progress_human" class="aicp_progress"></div>
            <div id="aicp_progress_mixed" class="aicp_progress"></div>
        </div>
        <div id="aicp_result"></div>
    `;
    
    let loadResultContent = aicpData.loadScanResult === 'progressChart' ? progressChart : progressBar;
    const sidebarContent = `
        <div class="aicp_content_container">
            <ul id="aicp_tabs">
                <li class="tab-active"><a href="#tab-1" rel="nofollow">Ai</a></li>
                <li><a href="#tab-2" rel="nofollow">Plagiarism</a></li>
            </ul>
            <div class="tabs-stage">
                <div class="aicp_container" id="tab-1" style="display: block;">
                    <div class="aicp_tab_inr">
                        ${loadResultContent}
                        <div class="aicp_scan_area">
                            <h2 class="aicp_sub_heading" id="aicp_sub_heading"></h2>
                            <button class="aicp_button" id="aicp_scan">Scan</button>
                        </div>
                        <div class="aicp_deep_scan_area">
                            <h2 class="aicp_sub_heading">Deep Scan</h2>
                            <p>The per-sentence score breakdown quantifies how much each sentence contributes to the model's overall Al probability score. Higher scores mean greater impact on the model's prediction. Green highlighting implies more human- like, and orange highlighting implies more Al-like. Hover over the bar below, and click the colored regions to bring the relevant sentence into view.</p>
                            <button class="aicp_button" id="aicp_deepscan">Run Scan</button>
                        </div>
                    </div>
                </div>
                <div class="aicp_container" id="tab-2" style="display: none;">
                    <div class="aicp_tab_inr">
                        
                    </div>
                </div>
            </div>
        </div>
    `;

    const AICPPanel = () => createElement(
        PluginSidebar,
        {
            name: "aicp-side-panel",
            title: "WP Writer",
            // icon: 'edit'
            icon: () =>
                createElement("img", {
                  src: aicpData.pluginUrl + 'assets/images/star-icon.svg',
                  alt: "Star Icon",
                  className: "wp-writer-icon"
                }),
        },
        createElement(RawHTML, {}, sidebarContent) // Use RawHTML to render the HTML content
    );

    registerPlugin('aicp-plugin-sidebar', {
        render: AICPPanel
    });

    // Ensure this block runs only once
    const unsubscribe = subscribe(() => {
        const isReady = selectCoreEditor.isCleanNewPost() || select('core/block-editor').getBlockCount() > 0;
        if ( isReady ) {
            var generalSidebarName = wp.data.select('core/edit-post').getActiveGeneralSidebarName();
            
            if ( generalSidebarName !='aicp-plugin-sidebar/aicp-side-panel' ) {
                //dispatch('core/edit-post').openGeneralSidebar('aicp-plugin-sidebar/aicp-side-panel'); // This is not working.
                $('button.components-button[aria-label="WP Writer"]').click();
                generalSidebarName = wp.data.select('core/edit-post').getActiveGeneralSidebarName();
                //console.log(generalSidebarName);
                if ( generalSidebarName =='aicp-plugin-sidebar/aicp-side-panel' ) {
                    unsubscribe();
                }
            }
        }
    });

    //For the tab change
    $(document).on("click", "#aicp_tabs a", function(event) {
        event.preventDefault();
        $(".tab-active").removeClass("tab-active");
        $(this).parent().addClass("tab-active");
        $(".tabs-stage .aicp_container").hide();
        $($(this).attr("href")).show();
    });
    
    function disablePublishBtn () {
        $('.editor-post-publish-button__button').prop('disabled', true);
    }

    function enablePublishBtn () {
        $('.editor-post-publish-button__button').prop('disabled', false);
    }

    function isPostSavingLocked() {
        return selectCoreEditor.isPostSavingLocked();
    }

    function lockPostSaving(message = null, messageType = 'info') {
        $('.editor-post-publish-button__button').addClass('lockPostSaving');
        $('#aicp_sub_heading').text('Publishing Locked');
        dispatchCoreEditor.lockPostSaving();

        if (message) {
            dispatchCoreNotices.removeAllNotices();
            dispatchCoreNotices.createNotice(messageType, message);
            //$('#aicp_result').text(message);
        }
    }

    function unlockPostSaving(source = 'unknown', message = null, messageType = 'info') {
        $('.editor-post-publish-button__button').removeClass('lockPostSaving');
        $('#aicp_sub_heading').text('');
        if( source == 'save_click' )
        {
            dispatchCoreEditor.editPost({ status: 'publish' });
            dispatchCoreEditor.savePost();
        }
        
        if (message) {
            dispatchCoreNotices.removeAllNotices();
            dispatchCoreNotices.createNotice(messageType, message);
            //$('#aicp_result').text(message);
        }
    }

    function analyzeContent(source = 'unknown') {

        const content = selectCoreEditor.getEditedPostContent();
        const postContent = content.replace(/<\/?[^>]+(>|$)|\r|\n/g, ""); // Strip HTML tags and line breaks

        console.log(postContent+' - analyzeContent Loaded from Gutenberg');

        // If content is not available, exit the function
        if (postContent.trim() === '') {
            lockPostSaving('Please write your content and click the publish button to verify and publish it');
            return;
        }

        lockPostSaving('Please hold on. We are analyzing the content');
        aiResponse = '';
        if (aicpData.loadScanResult == 'progressBar') {
            updateProgressBar (source, aiResponse);
        } else if(aicpData.loadScanResult == 'progressChart') {
            intializeChart (source, aiResponse);
        }
        
        $.ajax({
            url: aicpData.ajaxUrl,
            method: 'POST',
            data: {
                action: 'aicp_analyze_content',
                content: postContent
            },
            success: function(response) {
                aiResponse = response; // Assign the AJAX response to the global variable
                if (aicpData.loadScanResult == 'progressBar') {
                    updateProgressBar (source, response);
                } else if(aicpData.loadScanResult == 'progressChart') {
                    intializeChart(source, response);
                }
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

    function updateProgressBar (source, response) {
        let analyzeResult;
        const aiProgressDiv = $('#aicp_progress_ai');
        const humanProgressDiv = $('#aicp_progress_human');
        const mixedProgressDiv = $('#aicp_progress_mixed');
        if (!response) {
            aiProgressDiv.text('');
            humanProgressDiv.text('');
            mixedProgressDiv.text('');

            aiProgressDiv.width('');
            humanProgressDiv.width('');
            mixedProgressDiv.width('');
            return;
        }

        const probabilities = response.documents[0].class_probabilities;
        const aiPercentage = (probabilities.ai * 100).toFixed(2);
        const humanPercentage = (probabilities.human * 100).toFixed(2);
        const mixedPercentage = (probabilities.mixed * 100).toFixed(2);

        console.log(response);
        console.log('aiPercentage-'+aiPercentage+',humanPercentage-'+humanPercentage+',mixedPercentage-'+mixedPercentage);

        aiProgressDiv.width(`${aiPercentage}%`);
        humanProgressDiv.width(`${humanPercentage}%`);
        mixedProgressDiv.width(`${mixedPercentage}%`);
        
        if (parseFloat(aiPercentage) > parseFloat(aicpData.aicp_detect_threshold)) {
            aiProgressDiv.text(`${aiPercentage}%`);
            analyzeResult = `${aiPercentage}% of this content is likely AI-generated. Publishing is locked.`;
            lockPostSaving(analyzeResult, 'error');
        } else {
            humanProgressDiv.text(`${humanPercentage}%`);
            analyzeResult = `This content is human-generated.`;
            unlockPostSaving(source, analyzeResult, 'success');
        }
    }

    function intializeChart (source, response) {
        let analyzeResult;
        if (!response) {
            if ( myDoughnutChart ) {
                //myDoughnutChart.destroy();
            }
            return;
        }

        const probabilities = response.documents[0].class_probabilities;
        const aiPercentage = (probabilities.ai * 100).toFixed(2);
        const humanPercentage = (probabilities.human * 100).toFixed(2);
        const mixedPercentage = (probabilities.mixed * 100).toFixed(2);

        console.log(response);
        console.log('aiPercentage-'+aiPercentage+',humanPercentage-'+humanPercentage+',mixedPercentage-'+mixedPercentage);

        if (parseFloat(aiPercentage) > parseFloat(aicpData.aicp_detect_threshold)) {
            analyzeResult = `${aiPercentage}% of this content is likely AI-generated. Publishing is locked.`;
            lockPostSaving(analyzeResult, 'error');
        } else {
            analyzeResult = `This content is human-generated.`;
            unlockPostSaving(source, analyzeResult, 'success');
        }

        if( $('#myDoughnutChart').length > 0 )
        {
            if ( myDoughnutChart ) {
                myDoughnutChart.destroy();
            }
                
            var ctx = document.getElementById('myDoughnutChart').getContext('2d');
            myDoughnutChart = new Chart(ctx, {
                type: 'doughnut', // Define chart type
                data: {
                    labels: ['AI',  'Human'], // Labels for segments
                    datasets: [{
                        label: 'Colors Distribution',
                        data: [aiPercentage,  humanPercentage], // Data points for each segment
                        backgroundColor: [
                            '#ff4c4c', // Color for Red
                            '#4caf50'  // Color for Green
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true, // Adjust the chart size according to screen
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true, // Display legend
                            position: 'top' // Position of the legend
                        },
                        tooltip: {
                            enabled: true // Enable tooltips
                        }
                    }
                }
            });
        }
    }

    $(document).on("click", 'button.components-button[aria-label="WP Writer"]', function(event) {
        if (aicpData.loadScanResult == 'progressBar') {
            updateProgressBar (source = 'unknown', aiResponse);
        } else if(aicpData.loadScanResult == 'progressChart') {
            intializeChart (source = 'unknown', aiResponse);
        }
    });

    function checkContentLoaded() {
        // Function is used only in edit mode 
        const content = selectCoreEditor.getEditedPostContent();
        if (content.trim() !== '') {
            analyzeContent('window_load');
        } else {
            console.log('retry by setTimeout');
            setTimeout(checkContentLoaded, 500); // Retry after 500ms if content is empty
        }
    }

    function getPostIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('post');
    }

    $(window).on('load', function() {
        lockPostSaving('Publishing is locked. Please scan the document and verify.'); // Lock the post saving behaviour in Gutenberg
        
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

    $(document).on('click', '#aicp_scan', function(event) {
        event.preventDefault();
        analyzeContent('scan');
    });
});