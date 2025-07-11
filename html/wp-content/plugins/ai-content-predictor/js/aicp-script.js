jQuery(document).ready(function($) {
    function disablePublishBtn () {
        $('.editor-post-publish-button__button').prop('disabled', true);
    }
    function enablePublishBtn () {
        $('.editor-post-publish-button__button').prop('disabled', false);
    }
    function analyzeContent() {

        if (!tinymce.activeEditor) {
            console.error('TinyMCE editor is not available.');
            return;
        }

        var analyzeResult;
        const resultDiv = $('#aicp_result');
        const aiProgressDiv = $('#aicp_progress_ai');
        const humanProgressDiv = $('#aicp_progress_human');
        const mixedProgressDiv = $('#aicp_progress_mixed');

        const postContent = tinymce.activeEditor.getContent();
        //const postContent = $('#acf_content').val();

        //const postContent = "The Mystical Beauty of Sundarban The Sundarban, a sprawling delta region in the Bay of Bengal, is one of the most enchanting and ecologically diverse places on Earth. Straddling the border between India and Bangladesh, this UNESCO World Heritage Site is renowned for its dense mangrove forests, rich biodiversity, and unique cultural heritage. A Natural Wonder The Sundarban is home to the largest mangrove forest in the world. These unique trees thrive in the brackish waters of the delta, creating a labyrinthine network of waterways, islands, and mudflats. This intricate ecosystem supports an astonishing variety of wildlife, including over 400 species of fish, 300 species of birds, and the iconic Royal Bengal Tiger.";
        
        console.log(postContent+' - analyzeContent Loaded from Tinymce');
        if (postContent.trim() === '') {
            resultDiv.text('Editor content is not yet available. AI is working on that.');
            return; // If content is not available, exit the function
        }

        $.ajax({
            url: aicpData.ajaxUrl,
            method: 'POST',
            data: {
                action: 'aicp_analyze_content',
                content: postContent
            },
            success: function(response) {
                console.error(response);
                if (response.data) {
                    if ( response.data.error ) {
                        alert(response.data.error);
                        disablePublishBtn();
                    }
                    else {
                        const probabilities = response.data.documents[0].class_probabilities;

                        const aiPercentage = probabilities.ai * 100;
                        const humanPercentage = probabilities.human * 100;
                        const mixedPercentage = probabilities.mixed * 100;

                        aiProgressDiv.width(`${aiPercentage}%`);
                        humanProgressDiv.width(`${humanPercentage}%`);
                        mixedProgressDiv.width(`${mixedPercentage}%`);

                        if (aiPercentage >= 60) {
                            aiProgressDiv.text(`${aiPercentage}%`);
                            analyzeResult = `${aiPercentage}% of this content is likely AI-generated. Publishing is disabled.`;
                            resultDiv.text(analyzeResult);
                            createNotice('error', analyzeResult);
                            disablePublishBtn();
                        } else if (humanPercentage >= 60) {
                            humanProgressDiv.text(`${humanPercentage}%`);
                            analyzeResult = `This content is human-generated.`;
                            resultDiv.text(analyzeResult);
                            enablePublishBtn();
                        } else {
                            mixedProgressDiv.text(`${mixedPercentage}%`);
                            analyzeResult = `This content is a mix of AI and human.`;
                            resultDiv.text(analyzeResult);
                            enablePublishBtn();
                        }
                    }
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                let errorMessage = 'Error analyzing content.';
                if (XMLHttpRequest.responseJSON && XMLHttpRequest.responseJSON.error) {
                    errorMessage = XMLHttpRequest.responseJSON.error;
                }
                disablePublishBtn();
                resultDiv.text(errorMessage);
                alert(errorMessage);
            }
        });
    }

    // Analyze content when the editor content changes
    tinymce.activeEditor.on('change', function() {alert('change detected');
        analyzeContent();
    });

    // Analyze content when the page loads
    $(window).on('load', function() {
        analyzeContent();
    });

    // Wait until TinyMCE is fully initialized
    var checkEditor = setInterval(function() {
        if (tinymce && tinymce.activeEditor) {
            clearInterval(checkEditor);

            // Analyze content when the editor content changes
            tinymce.activeEditor.on('change', function() {alert('change detected');
                analyzeContent();
            });

            // Analyze content when the page loads
            analyzeContent();
        }
    }, 500); // Check every 500ms
});