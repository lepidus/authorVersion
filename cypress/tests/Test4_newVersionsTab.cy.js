import '../support/commands.js';

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

    it('Creates new submission as author', function () {
        cy.login('rrossi', null, 'publicknowledge');		
        cy.createSubmission(submissionData);
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