describe('Author Version - New versions tab', function () {
    let submissionData;
    
    before(function() {
        submissionData = {
            'title': 'Submission for testing new versions tab',
			'abstract': 'Just a simple abstract',
			'keywords': ['plugin', 'testing']
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

    it('Creates new submission as author', function () {
        cy.login('rrossi', null, 'publicknowledge');
		cy.get('div#myQueue a:contains("New Submission")').click();

        step1();
        step2();
        step3();
        step4();

        cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
		cy.get('a:contains("Proceed to post")').click();
        
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