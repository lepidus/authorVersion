import '../support/commands.js';

describe('Author Version - Submission relations updating', function () {
    let publishedArticleDoi = 'https://doi.org/10.1234/Non-existentDoi';

    it('Author can change relations on posted preprint version', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.openAuthorVersionSubmission('Submission for testing Author Version plugin', 'My Submissions as Author');
        cy.get('button:contains("Relations")');
        cy.logout();

        cy.login('dbarnes', null, 'publicknowledge');
        cy.openAuthorVersionSubmission('Submission for testing Author Version plugin', 'Submissions with new version');
        cy.openWorkflowMenu('Title & Abstract');
        cy.postAuthorVersion();
        cy.contains('[data-cy="active-modal"]', 'Posted');
        cy.logout();

        cy.login('zwoods', null, 'publicknowledge');
        cy.openAuthorVersionSubmission('Submission for testing Author Version plugin', 'My Submissions as Author');
        cy.contains('[data-cy="active-modal"]', 'Posted');
        cy.get('button:contains("Relations")');
        cy.logout();

        cy.login('dbarnes', null, 'publicknowledge');
        cy.openAuthorVersionSubmission('Submission for testing Author Version plugin', 'Published');
        cy.openWorkflowMenu('Title & Abstract');
        cy.unpostAuthorVersion();
        cy.contains('[data-cy="active-modal"]', 'Unpublished');
    });
    it('Author changes preprint relation to "published as an article"', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.openAuthorVersionSubmission('Submission for testing Author Version plugin', 'My Submissions as Author');
        
        cy.get('button:contains("Relations")').click();
        cy.get('.pkpWorkflow__publicationRelation input[value="3"]').click();
        cy.get('.pkpWorkflow__publicationRelation input[name="vorDoi"]').clear().type(publishedArticleDoi, {delay: 0});
        cy.intercept('POST', '**/publications/*/relate').as('updateRelation');
        cy.get('.pkpWorkflow__publicationRelation button:contains("Save")').click();
        cy.wait('@updateRelation').its('response.statusCode').should('eq', 200);
        cy.get('button:contains("Relations")').should('be.visible');
        cy.get('.pkpWorkflow__publicationRelation input[name="vorDoi"]').should('have.value', publishedArticleDoi);
        cy.get('button:contains("Create New Version")').should('not.exist');
    });
    it('Moderator posts last version of submission', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openAuthorVersionSubmission('Submission for testing Author Version plugin', 'Submissions with new version');
        
        cy.openWorkflowMenu('Title & Abstract');
        cy.postAuthorVersion();
        cy.contains('[data-cy="active-modal"]', 'Posted');
        cy.get('button:contains("Relations")').should('be.visible');
        cy.get('button:contains("Relations")').click();
        cy.get('.pkpWorkflow__publicationRelation input[name="vorDoi"]').should('have.value', publishedArticleDoi);
    });
    it('Author can not change relations or create new version anymore', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.openAuthorVersionSubmission('Submission for testing Author Version plugin', 'My Submissions as Author');

        cy.get('button:contains("Relations")').should('not.exist');
        cy.contains('[data-cy="active-modal"] .authorVersion__relation', publishedArticleDoi).should('be.visible');
        cy.get('button:contains("Create New Version")').should('not.exist');
        cy.window().then(win => {
            const workflow = win.pkp.registry.getPiniaStore('workflow');
            const submissionId = workflow.submission.id;
            const publicationId = workflow.selectedPublication.id;
            const publicationCount = workflow.submission.publications.length;
            cy.request({
                method: 'POST',
                url: `index.php/publicknowledge/api/v1/authorVersionCreation/${submissionId}/publications/${publicationId}/version`,
                headers: {'X-Csrf-Token': win.pkp.currentUser.csrfToken},
                failOnStatusCode: false,
            }).its('status').should('eq', 400);
            cy.request({
                method: 'PUT',
                url: `index.php/publicknowledge/api/v1/authorVersionRelations/${submissionId}/publications/${publicationId}/relate`,
                headers: {'X-Csrf-Token': win.pkp.currentUser.csrfToken},
                body: {relationStatus: 1, vorDoi: ''},
                failOnStatusCode: false,
            }).its('status').should('eq', 400);
            cy.request(`index.php/publicknowledge/api/v1/submissions/${submissionId}`).then(response => {
                expect(response.body.publications).to.have.length(publicationCount);
                const publication = response.body.publications.find(item => item.id === publicationId);
                expect(publication.relationStatus).to.eq(3);
                expect(publication.vorDoi).to.eq(publishedArticleDoi);
            });
        });
    });
});
