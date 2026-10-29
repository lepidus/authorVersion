import '../support/commands.js';

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

    it('Author - Creates new submission', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.createSubmission(submissionData);
    });
    it("Moderator - Can't delete first version", function () {
        cy.findSubmissionAsEditor('dbarnes', null, submissionData.title);
		cy.openWorkflowMenu('Title & Abstract');

        cy.get('button:contains("Delete version")').should('not.exist');
        cy.postAuthorVersion();
    });
    it('Author - Creates and submits new version', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionData.title, 'My Submissions as Author');
        
        cy.createAuthorVersion();
        
        cy.get('button:contains("Submit New Version")').click();
        cy.saveAuthorVersionJustification('Valid reason to submit a version');
    });
    it("Moderator - Checks can delete last version if it isn't posted", function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionData.title, 'New versions');
		
        cy.openWorkflowMenu('Title & Abstract');

        cy.get('button:contains("Delete version")');
        cy.postAuthorVersion();

        cy.get('button:contains("Delete version")').should('not.exist');
        cy.unpostAuthorVersion();
        
        cy.get('button:contains("Delete version")');
        cy.postAuthorVersion();
    });
    it('Author - Creates new version without submitting', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionData.title, 'My Submissions as Author');
        
        cy.createAuthorVersion();
    });
    it("Moderator - Deletes last version", function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionData.title, 'Active submissions');
		
        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button:contains("Delete version")').click();
        
        cy.contains('Are you sure you want to delete this version?');
        cy.contains('label', 'Justification for deleting');
        cy.contains('Provide a justification for deleting this version. This justification will be sent to the author.');
        cy.get('input[name="deletingJustification"]').clear().type('This version should not have been created', {delay: 0});

        cy.intercept('POST', '**/authorVersion/deleteVersion*').as('deleteAuthorVersion');
        cy.contains('[data-cy="active-modal"] form button', 'Delete').click();
        cy.wait('@deleteAuthorVersion').its('response.statusCode').should('eq', 200);
        cy.contains('[data-cy="active-modal"]', 'Posted');
        cy.contains('[data-cy="active-modal"] button', 'All Versions').click();
        cy.contains('[role="menu"] button', /^\s*Version 2:/).should('exist');
        cy.contains('[role="menu"] button', /^\s*Version 3:/).should('not.exist');
    });
});