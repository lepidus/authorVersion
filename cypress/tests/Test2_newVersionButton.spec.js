describe('Author Version - Create new version', function () {
    let submissionData;
    
    before(function() {
        submissionData = {
            title: 'Submission for testing Author Version plugin',
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
        cy.login('zwoods', null, 'publicknowledge');
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
		cy.contains('h2', 'Submission complete');
		cy.contains('a', 'Review this submission').click();

        cy.get('button:contains("Post")').should('not.exist');
        cy.get('button:contains("Submit New Version")').should('not.exist');
        
        cy.logout();
    });
    it('Post submission', function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Woods');
        cy.get('#publication-button').click();
		cy.get('.pkpHeader .pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
    });
    it('Button to create new version', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Create New Version")').click();
        
        cy.waitJQuery();
        cy.get('button:contains("Create New Version")').should('not.exist');
        
        cy.contains('All Versions').click();
        cy.get('.pkpPublication__versions button:contains("1")').click();
        cy.wait(1000);
        cy.get('button:contains("Create New Version")').should('not.exist');
    });
});