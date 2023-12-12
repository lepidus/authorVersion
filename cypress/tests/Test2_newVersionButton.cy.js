import '../support/commands.js';

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

    it('Creates new submission as author', function () {
        cy.login('zwoods', null, 'publicknowledge');
		
        cy.createSubmission(submissionData);
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