describe('Author Version - API access control', function () {
    beforeEach(function () {
        // Drop any session left by a request still running when the previous test ended
        cy.logout();
    });

    const deniedStatuses = [403, 404];
    let submissionId;

    function getCsrfTokenAs(username) {
        cy.login(username, null, 'publicknowledge');
        cy.visit('index.php/publicknowledge/user/profile');
        return cy.window().then((win) => win.pkp.currentUser.csrfToken);
    }

    function postToAuthorVersionApi(endpoint, csrfToken, body) {
        return cy.request({
            method: 'POST',
            url: 'index.php/publicknowledge/api/v1/authorVersion/' + endpoint + '?submissionId=' + submissionId,
            headers: {'X-Csrf-Token': csrfToken},
            body: body,
            failOnStatusCode: false
        });
    }

    before(function () {
        getCsrfTokenAs('dbarnes');
        cy.request('index.php/publicknowledge/api/v1/submissions?count=100').then((response) => {
            const submission = response.body.items.find((item) => JSON.stringify(item).includes('Woods'));
            expect(submission, 'submission authored by zwoods').to.exist;
            submissionId = submission.id;
        });
    });

    it('Author not assigned to the submission cannot submit or edit its version justification', function () {
        getCsrfTokenAs('ccorino').then((csrfToken) => {
            postToAuthorVersionApi('submitVersion', csrfToken, {versionJustification: 'Unauthorized justification'})
                .its('status').should('be.oneOf', deniedStatuses);
            postToAuthorVersionApi('versionJustification', csrfToken, {versionJustification: 'Unauthorized justification'})
                .its('status').should('be.oneOf', deniedStatuses);
        });
    });
    it('Moderator not assigned to the submission cannot edit justification or delete its version', function () {
        getCsrfTokenAs('minoue').then((csrfToken) => {
            postToAuthorVersionApi('versionJustification', csrfToken, {versionJustification: 'Unauthorized justification'})
                .its('status').should('be.oneOf', deniedStatuses);
            postToAuthorVersionApi('deleteVersion', csrfToken, {deletingJustification: 'Unauthorized deletion'})
                .its('status').should('be.oneOf', deniedStatuses);
        });
    });
    it('Authorized moderator gets 400 for justifications that are not text', function () {
        getCsrfTokenAs('dbarnes').then((csrfToken) => {
            postToAuthorVersionApi('submitVersion', csrfToken, {versionJustification: ['not', 'text']})
                .its('status').should('eq', 400);
            postToAuthorVersionApi('versionJustification', csrfToken, {versionJustification: {text: 'not text'}})
                .its('status').should('eq', 400);
            postToAuthorVersionApi('deleteVersion', csrfToken, {deletingJustification: ['not', 'text']})
                .its('status').should('eq', 400);
            postToAuthorVersionApi('versionJustification', csrfToken, {versionJustification: '   '})
                .its('status').should('eq', 400);
        });
    });
});
