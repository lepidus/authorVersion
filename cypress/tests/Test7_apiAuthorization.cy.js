import '../support/commands.js';

// Reuse the posted preprint and unsubmitted new version created by Test4.
// Writes exercise the public API and the logged-in user's CSRF protection.
describe('Author Version - API publication scope and authorization', function () {
    const title = 'Submission for testing new versions tab';
    const apiRoot = 'index.php/publicknowledge/api/v1';
    let submissionId;
    let publicationId;
    let previousPublicationId;
    let coreDeniedStatus;

    function loginApiUser(username, password, context = 'publicknowledge') {
        cy.logout();
        cy.login(username, password, context);
    }

    function requestVersion(operation, body, publication = publicationId, withCsrf = true) {
        return cy.window().then(win => cy.request({
            method: 'POST',
            url: `${apiRoot}/authorVersion/${operation}`,
            qs: {submissionId, ...(publication === null ? {} : {publicationId: publication})},
            headers: withCsrf ? {'X-Csrf-Token': win.pkp.currentUser.csrfToken} : {},
            body,
            failOnStatusCode: false,
        }));
    }

    function requestCreation(publication = publicationId, withCsrf = true) {
        return cy.window().then(win => cy.request({
            method: 'POST',
            url: `${apiRoot}/authorVersionCreation/${submissionId}/publications/${publication}/version`,
            headers: withCsrf ? {'X-Csrf-Token': win.pkp.currentUser.csrfToken} : {},
            failOnStatusCode: false,
        }));
    }

    function requestRelation(withCsrf = true) {
        return cy.window().then(win => cy.request({
            method: 'PUT',
            url: `${apiRoot}/authorVersionRelations/${submissionId}/publications/${publicationId}/relate`,
            headers: withCsrf ? {'X-Csrf-Token': win.pkp.currentUser.csrfToken} : {},
            body: {relationStatus: 1, vorDoi: ''},
            failOnStatusCode: false,
        }));
    }

    function checkJustification(expected) {
        cy.request(`${apiRoot}/submissions/${submissionId}`).then(response => {
            const publication = response.body.publications.find(item => item.id === publicationId);
            expect(publication.versionJustification || null).to.equal(expected);
        });
    }

    before(function () {
        loginApiUser('rrossi', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(title);
        cy.window().then(win => {
            const workflow = win.pkp.registry.getPiniaStore('workflow');
            submissionId = workflow.submission.id;
            publicationId = workflow.selectedPublication.id;
            previousPublicationId = workflow.submission.publications.find(item => item.id !== publicationId).id;
        });
    });

    it('Requires the publication id and rejects a stale publication without changing the draft', function () {
        loginApiUser('rrossi', null, 'publicknowledge');
        requestVersion('submitVersion', {versionJustification: 'Missing publication'}, null)
            .its('status').should('eq', 400);
        requestVersion('submitVersion', {versionJustification: 'Stale publication'}, previousPublicationId)
            .its('status').should('eq', 400);
        requestVersion('versionJustification', {versionJustification: 'Stale edit'}, previousPublicationId)
            .its('status').should('eq', 400);
        requestCreation(previousPublicationId).its('status').should('eq', 400);
        requestCreation().its('status').should('eq', 400);
        checkJustification(null);
    });

    it('Denies writes without CSRF and preserves the draft', function () {
        loginApiUser('rrossi', null, 'publicknowledge');
        requestCreation(publicationId, false).its('status').should('eq', 403);
        requestRelation(false).its('status').should('eq', 403);
        requestVersion('submitVersion', {versionJustification: 'Missing CSRF'}, publicationId, false)
            .its('status').should('eq', 403);
        requestVersion('versionJustification', {versionJustification: 'Missing CSRF edit'}, publicationId, false)
            .its('status').should('eq', 403);
        checkJustification(null);
    });

    it('Denies another author the same submission denied by the core', function () {
        loginApiUser('zwoods', null, 'publicknowledge');
        cy.request({url: `${apiRoot}/submissions/${submissionId}`, failOnStatusCode: false})
            .then(response => {
                expect(response.status).to.equal(401);
                coreDeniedStatus = response.status;
            });
        requestCreation().then(response => expect(response.status).to.eq(coreDeniedStatus));
        requestRelation().then(response => expect(response.status).to.eq(coreDeniedStatus));
        requestVersion('submitVersion', {versionJustification: 'Another author'})
            .then(response => expect(response.status).to.eq(coreDeniedStatus));
        requestVersion('versionJustification', {versionJustification: 'Another author'})
            .then(response => expect(response.status).to.eq(coreDeniedStatus));
        requestVersion('deleteVersion', {deletingJustification: 'Another author'})
            .its('status').should('eq', 401);
        loginApiUser('rrossi', null, 'publicknowledge');
        checkJustification(null);
    });

    it('Denies an unassigned moderator the same submission denied by the core', function () {
        loginApiUser('minoue', null, 'publicknowledge');
        cy.request({url: `${apiRoot}/submissions/${submissionId}`, failOnStatusCode: false})
            .then(response => {
                expect(response.status).to.equal(401);
                coreDeniedStatus = response.status;
            });
        requestCreation().then(response => expect(response.status).to.eq(coreDeniedStatus));
        requestRelation().then(response => expect(response.status).to.eq(coreDeniedStatus));
        requestVersion('submitVersion', {versionJustification: 'Unassigned moderator'})
            .then(response => expect(response.status).to.eq(coreDeniedStatus));
        requestVersion('versionJustification', {versionJustification: 'Unassigned moderator'})
            .then(response => expect(response.status).to.eq(coreDeniedStatus));
        requestVersion('deleteVersion', {deletingJustification: 'Unassigned moderator'})
            .then(response => expect(response.status).to.eq(coreDeniedStatus));
        loginApiUser('rrossi', null, 'publicknowledge');
        checkJustification(null);
    });

    it('Allows the owning author to submit and edit, and denies deleting', function () {
        loginApiUser('rrossi', null, 'publicknowledge');
        requestRelation().its('status').should('eq', 200);
        requestVersion('submitVersion', {versionJustification: 'API owner submission'})
            .its('status').should('eq', 200);
        requestVersion('versionJustification', {versionJustification: 'API owner edit'})
            .its('status').should('eq', 200);
        requestVersion('deleteVersion', {deletingJustification: 'Author cannot delete'})
            .its('status').should('eq', 401);
        checkJustification('API owner edit');
    });

    it('Denies the original submission through another server context', function () {
        const otherContext = 'authorversionisolation';
        loginApiUser('admin', 'admin', 'publicknowledge');
        cy.window().then(win => cy.request({
            method: 'POST',
            url: 'index.php/index/api/v1/contexts',
            headers: {'X-Csrf-Token': win.pkp.currentUser.csrfToken},
            body: {
                name: {en: 'Author Version isolation server'},
                acronym: {en: 'AVIS'},
                primaryLocale: 'en',
                supportedLocales: ['en'],
                urlPath: otherContext,
                contactName: 'Isolation test',
                contactEmail: 'authorversion-isolation@example.test',
                country: 'BR',
                enabled: true,
            },
        })).its('status').should('eq', 200);
        cy.visit(`index.php/${otherContext}/management/settings/website`);
        cy.get('button#plugins-button').click();
        cy.get('input[id^=select-cell-authorversionplugin]').check();
        cy.get('input[id^=select-cell-authorversionplugin]').should('be.checked');
        loginApiUser('rrossi', null, 'publicknowledge');
        cy.request({
            url: `index.php/${otherContext}/api/v1/submissions/${submissionId}`,
            failOnStatusCode: false,
        }).then(response => {
            expect(response.status).to.be.oneOf([401, 403]);
            coreDeniedStatus = response.status;
        });
        cy.window().then(win => {
            const headers = {'X-Csrf-Token': win.pkp.currentUser.csrfToken};
            cy.request({
                method: 'POST',
                url: `index.php/${otherContext}/api/v1/authorVersionCreation/${submissionId}/publications/${previousPublicationId}/version`,
                headers,
                failOnStatusCode: false,
            }).then(response => expect(response.status).to.eq(coreDeniedStatus));
            cy.request({
                method: 'PUT',
                url: `index.php/${otherContext}/api/v1/authorVersionRelations/${submissionId}/publications/${publicationId}/relate`,
                headers,
                body: {relationStatus: 1, vorDoi: ''},
                failOnStatusCode: false,
            }).then(response => expect(response.status).to.eq(coreDeniedStatus));
            ['submitVersion', 'versionJustification', 'deleteVersion'].forEach(operation => {
                cy.request({
                    method: 'POST',
                    url: `index.php/${otherContext}/api/v1/authorVersion/${operation}`,
                    qs: {submissionId, publicationId},
                    headers,
                    body: {versionJustification: 'Foreign context', deletingJustification: 'Foreign context'},
                    failOnStatusCode: false,
                }).then(response => expect(response.status).to.eq(coreDeniedStatus));
            });
        });
        checkJustification('API owner edit');
    });

    it('Allows a moderator after the core assigns them to this submission', function () {
        cy.findSubmissionAsEditor('dbarnes', null, title, 'publicknowledge', 'Submissions with new version');
        cy.openWorkflowMenu('Production');
        cy.assignParticipant('Moderator', 'Minoti Inoue');
        loginApiUser('minoue', null, 'publicknowledge');
        cy.request(`${apiRoot}/submissions/${submissionId}`).its('status').should('eq', 200);
        requestVersion('versionJustification', {versionJustification: 'Assigned moderator edit'})
            .its('status').should('eq', 200);
        checkJustification('Assigned moderator edit');
        requestVersion('deleteVersion', {deletingJustification: 'Stale publication'}, previousPublicationId)
            .its('status').should('eq', 400);
        requestVersion('deleteVersion', {deletingJustification: 'Missing CSRF delete'}, publicationId, false)
            .its('status').should('eq', 403);
        checkJustification('Assigned moderator edit');
        cy.request(`${apiRoot}/submissions/${submissionId}`).then(response => {
            expect(response.body.publications.map(item => item.id)).to.include(publicationId);
            expect(response.body.publications.map(item => item.id)).to.include(previousPublicationId);
        });
        requestVersion('deleteVersion', {deletingJustification: 'Assigned moderator deletes draft'})
            .its('status').should('eq', 200);
        cy.request(`${apiRoot}/submissions/${submissionId}`).then(response => {
            expect(response.body.publications.map(item => item.id)).not.to.include(publicationId);
            expect(response.body.publications.map(item => item.id)).to.include(previousPublicationId);
        });
    });
});
