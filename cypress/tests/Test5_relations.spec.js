describe('Author Version - Submission relations updating', function () {
    let publishedArticleDoi = 'https://doi.org/10.1234/Non-existentDoi';

    it('Author can change relations on posted preprint version', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#myQueue-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Relations")');
        cy.logout();

        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('#publication-button').click();
        cy.get('.pkpHeader .pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.get('.pkpPublication__statusPublished');
        cy.logout();

        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('.pkpPublication__statusPublished');
        cy.get('button:contains("Relations")');
        cy.logout();

        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('#publication-button').click();
        cy.get('button:contains("Unpost")').click();
        cy.get('.modal button:contains("Unpost")').click();
        cy.get('.pkpPublication__statusUnpublished');
    });
    it('Author changes preprint relation to "published as an article"', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#myQueue-button').click();
        cy.contains('View Woods').click({force: true});
        
        cy.get('button:contains("Relations")').click();
        cy.get('.pkpWorkflow__relateForm input[value="3"]').click();
        cy.get('.pkpWorkflow__relateForm input[name="vorDoi"]').clear().type(publishedArticleDoi, {delay: 0});
        cy.get('.pkpWorkflow__relateForm button:contains("Save")').click();

        cy.reload();
        cy.get('button:contains("Relations")');
    });
    it('Moderator posts last version of submission', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Woods').click({force: true});
        
        cy.get('#publication-button').click();
        cy.get('.pkpHeader .pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.get('.pkpPublication__statusPublished');
    });
    it('Author can not change relations or create new version anymore', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});

        cy.get('button:contains("Relations")').should('not.exist');
        cy.get('button:contains("Create New Version")').should('not.exist');
    });
});