describe('Author Version - Delete versions', function () {
    let submissionData;

    before(function() {
        submissionData = {
            'title': 'Submission for testing deleting of versions',
            'abstract': 'Just a simple abstract',
            'keywords': ['plugin', 'testing', 'delete']
        }
    });

    function step1() {
        cy.get('input[id^="checklist-"]').click({ multiple: true });
        cy.get('input[id=privacyConsent]').click();
        cy.get('button.submitFormButton').click();
    }

    function step2() {
        cy.get('#submitStep2Form button.submitFormButton').click();
    }

    function step3() {
        cy.get('input[name^="title"]').first().type(submissionData.title, { delay: 0 });
        cy.get('label').contains('Title').click();
        cy.get('textarea[id^="abstract-"').then((node) => {
            cy.setTinyMceContent(node.attr("id"), submissionData.abstract);
        });
        cy.get('.section > label:visible').first().click();
        cy.get('ul[id^="en_US-keywords-"]').then(node => {
            node.tagit('createTag', submissionData.keywords[0]);
            node.tagit('createTag', submissionData.keywords[1]);
        });

        cy.get('#submitStep3Form button.submitFormButton').click();
    }

    function step4() {
        cy.waitJQuery();
        cy.get('#submitStep4Form button.submitFormButton').click();
        cy.get('button.pkpModalConfirmButton').click();
    }

    it('Author - Creates new submission', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.get('div#myQueue a:contains("New Submission")').click();

        step1();
        step2();
        step3();
        step4();

        cy.waitJQuery();
        cy.get('h2:contains("Submission complete")');
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
        cy.get('#submitVersionModal button:contains("Submit")').click();
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
        cy.get('.modal button:contains("Unpost")').click();
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
        cy.get('.modal button:contains("Delete")').click();
        cy.waitJQuery();

        cy.get('.pkpPublication__version').contains('2');
        cy.get('.pkpPublication__statusPublished').contains('Posted');
    });
});