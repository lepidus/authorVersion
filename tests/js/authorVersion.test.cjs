const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup() {
    const extensions = {};
    const components = {};
    const calls = {refresh: 0, watchers: []};
    const store = {
        extender: {extendFn: (name, callback) => { extensions[name] = callback; }},
        selectPublicationId: (id) => { calls.selected = id; },
    };
    const form = {fields: [{name: 'versionJustification', value: ''}]};
    const pkp = {
        const: {WORKFLOW_STAGE_ID_PRODUCTION: 5, ROLE_ID_AUTHOR: 65536, ROLE_ID_MANAGER: 16, ROLE_ID_SITE_ADMIN: 1, ROLE_ID_SUB_EDITOR: 17, STATUS_QUEUED: 1, STATUS_PUBLISHED: 3},
        plugins: {authorVersion: {
            relationPublished: 3, apiUrl: '/api/v1/authorVersion',
            creationApiUrl: '/api/v1/authorVersionCreation/__submissionId__/publications/__publicationId__/version',
            submitVersionForm: {...form, action: '/api/v1/authorVersion/submitVersion'},
            versionJustificationForm: {...form, action: '/api/v1/authorVersion/versionJustification'},
            deleteVersionForm: {...form, action: '/api/v1/authorVersion/deleteVersion'},
            relationForm: {
                action: '/api/v1/authorVersionRelations/__submissionId__/publications/__publicationId__/relate',
                method: 'PUT',
                fields: [{name: 'relationStatus', value: null}, {name: 'vorDoi', value: null}],
            },
        }},
        modules: {
            vue: {
                h: (component, props, children) => ({component, props, children}),
                inject: () => () => {},
                watch: (getter, callback, options) => {
                    calls.watchers.push(() => callback(getter()));
                    if (options.immediate) callback(getter());
                },
            },
            useForm: {useForm: (form) => ({form: {value: form}, set: () => {}})},
            useModal: {useModal: () => ({
                openSideModal: (component, props) => { calls.modal = {component, props}; },
                openDialog: (props) => { calls.dialog = props; },
            })},
            useFetch: {useFetch: (url, options) => {
                calls.request = {url, options};
                const data = {value: null};
                const isSuccess = {value: null};
                const isLoading = {value: false};
                return {data, isSuccess, isLoading, fetch: async () => {
                    calls.fetchCount = (calls.fetchCount || 0) + 1;
                    data.value = calls.response || null;
                    isSuccess.value = !calls.fetchFails;
                }};
            }},
            useDataChanged: {useDataChanged: () => ({triggerDataChange: () => calls.refresh++})},
            useSubmission: {useSubmission: () => ({getLatestPublication: ({publications}) => publications.reduce((a, b) => a.id > b.id ? a : b)})},
            useLocalize: {useLocalize: () => ({t: (key) => key})},
        },
        registry: {
            registerComponent: (name, component) => { components[name] = component; },
            getComponent: (name) => components[name] || name,
            getPiniaStore: () => store,
            storeExtend: (name, callback) => callback({store}),
        },
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../js/authorVersion.js'), 'utf8'), {
        pkp, URL, window: {location: {href: 'https://example.org/ops/index.php/server/dashboard'}},
    });
    function args(publication, roles = [65536], otherPublications = []) {
        return {
            selectedMenuState: {primaryMenuItem: 'publication'}, selectedPublication: publication,
            submission: {id: 10, publications: [...otherPublications, publication], stages: [{id: 5, currentUserAssignedRoles: roles}]},
        };
    }
    return {extensions, components, calls, pkp, args, store};
}

test('author can create a version only from the latest published preprint without published relation', () => {
    const {extensions, args} = setup();
    const publication = {id: 2, version: 1, status: 3};
    const controls = (a) => extensions.getPrimaryControlsRight([], a);
    assert.equal(controls(args(publication))[0].component, 'AuthorVersionCreateAction');
    assert.equal(controls(args({...publication, relationStatus: 3})).length, 0);
    assert.equal(controls(args(publication, [65536], [{id: 3, status: 1}])).length, 0);
    assert.equal(controls(args(publication, [])).length, 0);
});

test('author create uses the dedicated POST endpoint and selects the successful result', async () => {
    const {components, args, calls} = setup();
    const publication = {id: 2, version: 1, status: 3};
    const submission = args(publication).submission;
    components.AuthorVersionCreateAction.setup({publication, submission})().props.onClick();
    assert.equal(calls.request.url, '/api/v1/authorVersionCreation/10/publications/2/version');
    assert.equal(calls.request.options.method, 'POST');
    assert.equal(calls.fetchCount, undefined);
    calls.response = {id: 3};
    let closed = false;
    await calls.dialog.actions[0].callback(() => { closed = true; });
    assert.equal(calls.selected, 3);
    assert.equal(calls.refresh, 1);
    assert.equal(closed, true);
});

test('author create leaves state intact on failed or empty responses and cancellation', async () => {
    const {components, args, calls} = setup();
    const publication = {id: 2, version: 1, status: 3};
    components.AuthorVersionCreateAction.setup({publication, submission: args(publication).submission})().props.onClick();
    let closed = false;
    calls.fetchFails = true;
    await calls.dialog.actions[0].callback(() => { closed = true; });
    assert.equal(calls.selected, undefined);
    assert.equal(calls.refresh, 0);
    assert.equal(closed, false);
    calls.fetchFails = false;
    await calls.dialog.actions[0].callback(() => { closed = true; });
    assert.equal(calls.selected, undefined);
    assert.equal(calls.refresh, 0);
    calls.dialog.actions[1].callback(() => { closed = true; });
    assert.equal(closed, true);
    assert.equal(calls.fetchCount, 2);
});

test('draft submission and editor deletion obey version, assignment and recommendation state', () => {
    const {extensions, args} = setup();
    const publication = {id: 2, version: 2, status: 1};
    const operations = (a) => Array.from(extensions.getPrimaryControlsRight([], a), (item) => item.props.operation);
    assert.deepEqual(operations(args(publication)), ['submitVersion']);
    assert.deepEqual(operations(args(publication, [16])), ['submitVersion', 'deleteVersion']);
    assert.deepEqual(operations(args({...publication, versionJustification: 'Reason'}, [16])), ['deleteVersion']);
    assert.deepEqual(operations(args({...publication, version: 1}, [16])), []);
    assert.deepEqual(operations(args(publication, [])), []);
    const recommendOnly = args(publication, [17]);
    recommendOnly.submission.stages[0].currentUserCanRecommendOnly = true;
    assert.deepEqual(operations(recommendOnly), []);
});

test('published relations are read-only and previous justifications cannot be edited', () => {
    const {extensions, args} = setup();
    const publication = {id: 2, version: 2, status: 3, relationStatus: 3, versionJustification: '<script>text</script>'};
    const relation = {component: 'WorkflowPublicationRelationDropdownOPS', props: {publication}};
    const result = extensions.getPrimaryControlsLeft([[relation]], args(publication, [65536], [{id: 3, status: 1}]));
    assert.equal(result[0][0].component, 'AuthorVersionPublishedRelation');
    assert.equal(result[1].props.canEdit, false);
    assert.equal(relation.component, 'WorkflowPublicationRelationDropdownOPS');
});

test('authors can still edit the relation of an unpublished version', () => {
    const {extensions, args, store} = setup();
    store.dashboardPage = 'mySubmissions';
    const publication = {id: 2, version: 2, status: 1, relationStatus: 3};
    const relation = {component: 'WorkflowPublicationRelationDropdownOPS', props: {publication}};
    const result = extensions.getPrimaryControlsLeft([[relation]], args(publication));
    assert.equal(result[0][0].component, 'AuthorVersionRelationDropdown');
});

test('author relation forms use native relate routing, isolate fields and refresh after success', () => {
    const {components, calls, pkp} = setup();
    const props = {publication: {id: 2, submissionId: 10, relationStatus: 3, vorDoi: '10.1000/first'}};
    const render = components.AuthorVersionRelationDropdown.setup(props);
    const form = render().children();
    assert.equal(form.props.method, 'PUT');
    assert.equal(form.props.action, '/api/v1/authorVersionRelations/10/publications/2/relate');
    assert.equal(form.props.fields[0].value, 3);
    assert.equal(form.props.fields[1].value, '10.1000/first');
    form.props.fields[1].value = 'changed locally';
    assert.equal(pkp.plugins.authorVersion.relationForm.fields[1].value, null);
    assert.equal(calls.refresh, 0);
    form.props.onSuccess();
    assert.equal(calls.refresh, 1);
    props.publication = {id: 4, submissionId: 11, relationStatus: 1, vorDoi: null};
    calls.watchers[0]();
    const nextForm = render().children();
    assert.equal(nextForm.props.action, '/api/v1/authorVersionRelations/11/publications/4/relate');
    assert.equal(nextForm.props.fields[0].value, 1);
    assert.equal(nextForm.props.fields[1].value, null);
});

test('author relation URLs preserve query-based PKP routes and older version IDs', () => {
    const {components, pkp} = setup();
    pkp.plugins.authorVersion.relationForm.action = 'https://example.org/index.php?server=test&page=api&op=v1&path=authorVersionRelations%2F__submissionId__%2Fpublications%2F__publicationId__%2Frelate';
    const props = {publication: {id: 2, submissionId: 10, relationStatus: 1, vorDoi: null}};
    const form = components.AuthorVersionRelationDropdown.setup(props)().children();
    assert.equal(new URL(form.props.action).searchParams.get('path'), 'authorVersionRelations/10/publications/2/relate');
});

test('editors retain core relation and create controls for a posted version with a published relation', () => {
    const {extensions, args, store} = setup();
    store.dashboardPage = 'editorialDashboard';
    const publication = {id: 2, version: 2, status: 3, relationStatus: 3};
    const relation = {component: 'WorkflowPublicationRelationDropdownOPS', props: {publication}};
    const create = {component: 'WorkflowActionButton', props: {action: 'workflowCreateNewVersion'}};
    assert.equal(extensions.getPrimaryControlsLeft([[relation]], args(publication, [16]))[0][0], relation);
    assert.equal(extensions.getPrimaryControlsRight([create], args(publication, [16]))[0], create);
});

test('dual-role users follow the active dashboard for published-relation restrictions', () => {
    const {extensions, args, store} = setup();
    const publication = {id: 2, version: 2, status: 3, relationStatus: 3};
    const relation = {component: 'WorkflowPublicationRelationDropdownOPS', props: {publication}};
    const create = {component: 'WorkflowActionButton', props: {action: 'workflowCreateNewVersion'}};
    const context = args(publication, [65536, 16]);
    store.dashboardPage = 'mySubmissions';
    assert.equal(extensions.getPrimaryControlsLeft([[relation]], context)[0][0].component, 'AuthorVersionPublishedRelation');
    assert.equal(extensions.getPrimaryControlsRight([create], context).length, 0);
    store.dashboardPage = 'editorialDashboard';
    assert.equal(extensions.getPrimaryControlsLeft([[relation]], context)[0][0], relation);
    assert.equal(extensions.getPrimaryControlsRight([create], context)[0], create);
    delete store.dashboardPage;
    assert.equal(extensions.getPrimaryControlsLeft([[relation]], context)[0][0], relation);
    assert.equal(extensions.getPrimaryControlsRight([create], context)[0], create);
});

test('forms target selected submission and publication, isolate values, and refresh only after save', () => {
    const {components, args, calls, pkp} = setup();
    const publication = {id: 2, version: 2, status: 1, versionJustification: 'Updated'};
    const submission = args(publication).submission;
    const render = components.AuthorVersionAction.setup({publication, submission, operation: 'versionJustification', title: 'Reason'});
    render().props.onClick();
    assert.equal(calls.modal.props.formConfig.action, 'https://example.org/api/v1/authorVersion/versionJustification?submissionId=10&publicationId=2');
    assert.equal(calls.modal.props.formConfig.fields[0].value, 'Updated');
    assert.equal(pkp.plugins.authorVersion.versionJustificationForm.fields[0].value, '');
    assert.equal(calls.refresh, 0);
    calls.modal.props.onSaved();
    assert.equal(calls.refresh, 1);
});

test('deletion selects the remaining latest version before refreshing the workflow', () => {
    const {components, args, calls} = setup();
    const publication = {id: 3, version: 2, status: 1};
    const submission = args(publication, [16], [{id: 2, status: 3}]).submission;
    components.AuthorVersionAction.setup({publication, submission, operation: 'deleteVersion', title: 'Delete'})().props.onClick();
    calls.modal.props.onSaved();
    assert.equal(calls.selected, 2);
    assert.equal(calls.refresh, 1);
});

test('form URLs retain the PKP route when path_info is disabled', () => {
    const {components, args, calls, pkp} = setup();
    pkp.plugins.authorVersion.submitVersionForm.action = 'https://example.org/index.php?journal=server&page=api&op=v1&path=authorVersion%2FsubmitVersion';
    const publication = {id: 3, version: 2, status: 1};
    components.AuthorVersionAction.setup({publication, submission: args(publication).submission, operation: 'submitVersion'})().props.onClick();
    const url = new URL(calls.modal.props.formConfig.action);
    assert.equal(url.searchParams.get('path'), 'authorVersion/submitVersion');
    assert.equal(url.searchParams.get('submissionId'), '10');
    assert.equal(url.searchParams.get('publicationId'), '3');
});
