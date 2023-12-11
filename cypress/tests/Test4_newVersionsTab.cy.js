describe('Author Version - New versions tab', function () {
    let submissionData;
    
    before(function() {
        submissionData = {
            title: 'Submission for testing new versions tab',
			abstract: 'Just a simple abstract',
			keywords: ['plugin', 'testing'],
            files: [
                {
                    'file': 'dummy.pdf',
                    'fileName': 'dummy.pdf',
                    'mimeType': 'application/pdf',
                    'genre': 'Preprint Text'
                }
            ]
		}
    });

    function beginSubmission() {
        cy.get('input[name="locale"][value="en"]').click();
        cy.setTinyMceContent('startSubmission-title-control', submissionData.title);
        
        cy.get('input[name="submissionRequirements"]').check();
        cy.get('input[name="privacyConsent"]').check();
        cy.contains('button', 'Begin Submission').click();
    }

    function detailsStep() {
        cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        submissionData.keywords.forEach(keyword => {
            cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
            cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
        });
        cy.contains('button', 'Continue').click();
    }

    function filesStep() {
        cy.addSubmissionGalleys(submissionData.files);
        cy.contains('button', 'Continue').click();
    }

    it('Creates new submission as author', function () {
        cy.login('rrossi', null, 'publicknowledge');
		cy.get('div#myQueue a:contains("New Submission")').click();

        beginSubmission();
        detailsStep();
        filesStep();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Submit').click();
        cy.get('.modal__panel:visible').within(() => {
            cy.contains('button', 'Submit').click();
        });

        cy.waitJQuery();
		cy.contains('h1', 'Submission complete');
        
        cy.logout();
    });
    it('Post submission', function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Rossi');
        cy.get('#publication-button').click();
		cy.get('.pkpHeader .pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
    });
    it('Author creates new version', function () {
        cy.login('rrossi', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Rossi').click({force: true});
        cy.get('button:contains("Create New Version")').click();
    });
    it('Checks functioning of New Versions tab', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();

        cy.contains('Woods');
        cy.contains('Submission for testing Author Version plugin');
        cy.get('.listPanel__item--submission:visible').should('have.length', 1);

        cy.get('button:visible:contains("Filters")').click();
        cy.get('button:contains("Non-submitted")').click();
        cy.waitJQuery();

        cy.contains('Rossi');
        cy.contains(submissionData.title);
        cy.get('.listPanel__item--submission:visible').should('have.length', 1);
    });
});