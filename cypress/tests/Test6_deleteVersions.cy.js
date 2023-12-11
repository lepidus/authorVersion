describe('Author Version - Delete versions', function () {
    let submissionData;

    before(function() {
        submissionData = {
            title: 'Submission for testing deleting of versions',
            abstract: 'Just a simple abstract',
            keywords: ['plugin', 'testing', 'delete'],
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

    it('Author - Creates new submission', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
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
    });
    it("Moderator - Can't delete first version", function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Paglieri');
		cy.get('#publication-button').click();

        cy.get('button:contains("Delete version")').should('not.exist');
        cy.get('.pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
    });
    it('Author - Creates and submits new version', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Paglieri').click({force: true});
        
        cy.get('button:contains("Create New Version")').click();
        cy.waitJQuery();
        
        cy.get('button:contains("Submit New Version")').click();
        cy.get('input[name="versionJustification"]').clear().type('Valid reason to submit a version', {delay: 0});
        cy.get('form button:contains("Submit")').click();
    });
    it("Moderator - Checks can delete last version if it isn't posted", function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Paglieri').click({force: true});
		
        cy.get('#publication-button').click();

        cy.get('button:contains("Delete version")');
        cy.get('.pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.waitJQuery();

        cy.get('button:contains("Delete version")').should('not.exist');
        cy.get('button:contains("Unpost")').click();
        cy.get('.modal__panel button:contains("Unpost")').click();
        cy.waitJQuery();
        
        cy.get('button:contains("Delete version")');
        cy.get('.pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
    });
    it('Author - Creates new version without submitting', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Paglieri').click({force: true});
        
        cy.get('button:contains("Create New Version")').click();
        cy.waitJQuery();
    });
    it("Moderator - Deletes last version", function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Paglieri').click({force: true});
		
        cy.get('#publication-button').click();
        cy.get('button:contains("Delete version")').click();
        
        cy.contains('Are you sure you want to delete this version?');
        cy.contains('label', 'Justification for deleting');
        cy.contains('Provide a justification for deleting this version. This justification will be sent to the author.');
        cy.get('input[name="deletingJustification"]').clear().type('This version should not have been created', {delay: 0});

        cy.get('.modal button:contains("Delete")').click();
        cy.waitJQuery();

        cy.get('.pkpPublication__version').contains('2');
        cy.get('.pkpPublication__statusPublished').contains('Posted');
    });
});