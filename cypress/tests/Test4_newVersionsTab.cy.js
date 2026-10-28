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
        cy.findSubmissionAsEditor('dbarnes', null, submissionData.title);
        cy.openWorkflowMenu('Title & Abstract');
		cy.postAuthorVersion();
    });
    it('Author creates new version', function () {
        cy.login('rrossi', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionData.title, 'My Submissions as Author');
        cy.createAuthorVersion();
    });
    it('Checks functioning of New Versions tab', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('nav a', 'New versions').click();

        cy.contains('Woods');
        cy.contains('Submission for testing Author Version plugin');
        cy.get('table tbody tr').should('have.length', 1);

        cy.contains('nav a', 'Non-submitted new versions').click();

        cy.contains('Rossi');
        cy.contains(submissionData.title);
        cy.get('table tbody tr').should('have.length', 1);
    });
});