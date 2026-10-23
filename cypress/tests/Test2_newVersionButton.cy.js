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
		cy.visit('index.php/publicknowledge/dashboard/mySubmissions');
        cy.openAuthorVersionSubmission(submissionData.title);

        cy.get('button:contains("Post")').should('not.exist');
        cy.get('button:contains("Submit New Version")').should('not.exist');
        
        cy.logout();
    });
    it('Post submission', function () {
        cy.findSubmissionAsEditor('dbarnes', null, submissionData.title);
        cy.openWorkflowMenu('Title & Abstract');
		cy.postAuthorVersion();
    });
    it('Button to create new version', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionData.title, 'My Submissions as Author');
        cy.createAuthorVersion();
        cy.get('button:contains("Create New Version")').should('not.exist');
        
        cy.contains('All Versions').click();
        cy.contains('[role="menu"] button', /^\s*Version 1:/).click();
        cy.contains('[data-cy="active-modal"]', 'Posted').should('be.visible');
        cy.get('button:contains("Create New Version")').should('not.exist');
    });
});