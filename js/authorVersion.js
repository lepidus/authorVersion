/** OPS 3.5 workflow extensions. Uses the Vue runtime supplied by PKP. */
(function (pkp) {
    'use strict';

    const {h, inject, watch} = pkp.modules.vue;
    const {useForm} = pkp.modules.useForm;
    const {useModal} = pkp.modules.useModal;
    const {useDataChanged} = pkp.modules.useDataChanged;
    const {useSubmission} = pkp.modules.useSubmission;
    const {useLocalize} = pkp.modules.useLocalize;
    const {useFetch} = pkp.modules.useFetch;
    const config = pkp.plugins.authorVersion;
    const component = (name) => pkp.registry.getComponent(name);
    const label = (name, key) => config.labels?.[name] || useLocalize().t(key);

    function publicationState(submission, publication) {
        const latest = useSubmission().getLatestPublication(submission);
        const stage = submission.stages.find((stage) => stage.id === pkp.const.WORKFLOW_STAGE_ID_PRODUCTION);
        const roles = stage?.currentUserAssignedRoles || [];
        const isAuthor = roles.includes(pkp.const.ROLE_ID_AUTHOR);
        const isEditor = !stage?.currentUserCanRecommendOnly && roles.some((role) => [
            pkp.const.ROLE_ID_SITE_ADMIN, pkp.const.ROLE_ID_MANAGER, pkp.const.ROLE_ID_SUB_EDITOR,
        ].includes(role));
        return {
            latest,
            isAuthor,
            isEditor,
            canManage: isAuthor || isEditor,
            isLatest: latest?.id === publication.id,
            isDraft: publication.status === pkp.const.STATUS_QUEUED,
            relationPublished: publication.relationStatus === config.relationPublished,
        };
    }

    pkp.registry.registerComponent('AuthorVersionCreateAction', {
        props: ['publication', 'submission'],
        setup(props) {
            const {openDialog} = useModal();
            const {triggerDataChange} = useDataChanged();
            const store = pkp.registry.getPiniaStore('workflow');
            const title = label('createVersion', 'publication.createVersion');
            function open() {
                // PHP supplies a routed URL so query-based PKP installations work too.
                const url = config.creationApiUrl
                    .replace('__submissionId__', encodeURIComponent(props.submission.id))
                    .replace('__publicationId__', encodeURIComponent(props.publication.id));
                const {fetch, data, isSuccess, isLoading} = useFetch(url, {method: 'POST'});
                openDialog({
                    title,
                    message: label('createVersionConfirm', 'publication.version.confirm'),
                    actions: [
                        {
                            label: label('yes', 'common.yes'),
                            isPrimary: true,
                            callback: async (close) => {
                                if (isLoading.value) {
                                    return;
                                }
                                await fetch();
                                // useFetch already reports HTTP errors; leave the workflow untouched.
                                if (!isSuccess.value || !data.value?.id) {
                                    return;
                                }
                                store.selectPublicationId(data.value.id);
                                triggerDataChange();
                                close();
                            },
                        },
                        {label: label('no', 'common.no'), callback: (close) => close()},
                    ],
                    modalStyle: 'primary',
                });
            }
            return () => h(component('PkpButton'), {onClick: open}, () => title);
        },
    });

    pkp.registry.registerComponent('AuthorVersionFormModal', {
        props: ['title', 'formConfig', 'onSaved'],
        setup(props) {
            const closeModal = inject('closeModal');
            const {form, set} = useForm(props.formConfig);
            return () => h(component('PkpSideModalBody'), {}, {
                title: () => props.title,
                default: () => h(component('PkpSideModalLayoutBasic'), {}, {
                    default: () => h(component('PkpForm'), {
                        ...form.value,
                        onSet: set,
                        onSuccess: () => {
                            props.onSaved();
                            closeModal();
                        },
                    }),
                }),
            });
        },
    });

    pkp.registry.registerComponent('AuthorVersionAction', {
        props: ['publication', 'submission', 'operation', 'title', 'isWarnable'],
        setup(props) {
            const {openSideModal} = useModal();
            const {triggerDataChange} = useDataChanged();
            const store = pkp.registry.getPiniaStore('workflow');
            function open() {
                // Each modal owns its form: values and errors must not leak between submissions.
                const formConfig = JSON.parse(JSON.stringify(config[props.operation + 'Form']));
                const url = new URL(formConfig.action, window.location.href);
                url.searchParams.set('submissionId', props.submission.id);
                url.searchParams.set('publicationId', props.publication.id);
                formConfig.action = url.toString();
                formConfig.fields.forEach((field) => {
                    if (field.name === 'versionJustification') {
                        field.value = props.publication.versionJustification || '';
                    }
                });
                openSideModal('AuthorVersionFormModal', {
                    title: props.title,
                    formConfig,
                    onSaved: () => {
                        if (props.operation === 'deleteVersion') {
                            const remaining = props.submission.publications.filter((publication) => publication.id !== props.publication.id);
                            const latest = useSubmission().getLatestPublication({publications: remaining});
                            store.selectPublicationId(latest.id);
                        }
                        triggerDataChange();
                    },
                });
            }
            return () => h(component('PkpButton'), {onClick: open, isWarnable: props.isWarnable}, () => props.title);
        },
    });

    pkp.registry.registerComponent('AuthorVersionJustification', {
        props: ['publication', 'submission', 'canEdit'],
        setup(props) {
            return () => props.canEdit
                ? h(component('AuthorVersionAction'), {
                    publication: props.publication,
                    submission: props.submission,
                    operation: 'versionJustification',
                    title: label('versionJustification', 'plugins.generic.authorVersion.versionJustification'),
                })
                : h(component('PkpDropdown'), {
                    label: label('versionJustification', 'plugins.generic.authorVersion.versionJustification'),
                }, () => h('p', {class: 'authorVersion__justification'}, props.publication.versionJustification));
        },
    });

    pkp.registry.registerComponent('AuthorVersionRelationDropdown', {
        props: ['publication'],
        setup(props) {
            const {form, set} = useForm(JSON.parse(JSON.stringify(config.relationForm)));
            const {triggerDataChange} = useDataChanged();
            watch(() => props.publication, (publication) => {
                // Replacing the selected publication must also reset prior values and validation errors.
                const nextForm = JSON.parse(JSON.stringify(config.relationForm));
                nextForm.action = nextForm.action
                    .replace('__submissionId__', encodeURIComponent(publication.submissionId))
                    .replace('__publicationId__', encodeURIComponent(publication.id));
                nextForm.fields.forEach((field) => {
                    if (field.name === 'relationStatus' || field.name === 'vorDoi') {
                        field.value = publication[field.name];
                    }
                });
                form.value = nextForm;
            }, {immediate: true});
            return () => h(component('PkpDropdown'), {
                class: 'pkpWorkflow__publicationRelation',
                hasDropdownIcon: true,
                label: label('relation', 'publication.relation'),
            }, () => h(component('PkpForm'), {
                ...form.value,
                onSet: set,
                onSuccess: () => triggerDataChange(),
            }));
        },
    });

    pkp.registry.registerComponent('AuthorVersionPublishedRelation', {
        props: ['publication'],
        setup(props) {
            return () => h('span', {class: 'authorVersion__relation'}, [
                label('relation', 'publication.relation') + ': ',
                props.publication.vorDoi || label('published', 'publication.status.published'),
            ]);
        },
    });

    pkp.registry.storeExtend('workflow', ({store}) => {
        const isAuthorDashboard = (state) => store.dashboardPage
            ? store.dashboardPage === 'mySubmissions'
            : state.isAuthor && !state.isEditor;

        store.extender.extendFn('getPrimaryControlsLeft', (items, args) => {
            const {submission, selectedPublication: publication} = args;
            if (!submission || !publication || args.selectedMenuState.primaryMenuItem !== 'publication') {
                return items;
            }
            const state = publicationState(submission, publication);
            function restrictRelation(item) {
                if (Array.isArray(item)) {
                    return item.map(restrictRelation);
                }
                if (!isAuthorDashboard(state) || item.component !== 'WorkflowPublicationRelationDropdownOPS') {
                    return item;
                }
                return {
                    ...item,
                    component: publication.status === pkp.const.STATUS_PUBLISHED && state.relationPublished
                        ? 'AuthorVersionPublishedRelation' : 'AuthorVersionRelationDropdown',
                };
            }
            const result = (items || []).map(restrictRelation);
            if (publication.versionJustification) {
                result.push({
                    component: 'AuthorVersionJustification',
                    props: {publication, submission, canEdit: state.canManage && state.isLatest && state.isDraft},
                });
            }
            return result;
        });

        store.extender.extendFn('getPrimaryControlsRight', (items, args) => {
            const {submission, selectedPublication: publication} = args;
            if (!submission || !publication || args.selectedMenuState.primaryMenuItem !== 'publication') {
                return items;
            }
            const state = publicationState(submission, publication);
            const authorDashboard = isAuthorDashboard(state);
            const result = (items || []).filter((item) => !(authorDashboard && state.relationPublished && item.props?.action === 'workflowCreateNewVersion'));
            if (authorDashboard && state.isAuthor && state.isLatest && publication.status === pkp.const.STATUS_PUBLISHED && !state.relationPublished
                && !result.some((item) => item.props?.action === 'workflowCreateNewVersion')) {
                result.push({
                    component: 'AuthorVersionCreateAction',
                    props: {publication, submission},
                });
            }
            if (state.canManage && state.isLatest && state.isDraft && publication.version > 1 && !publication.versionJustification) {
                result.push({
                    component: 'AuthorVersionAction',
                    props: {publication, submission, operation: 'submitVersion', title: label('submitVersion', 'plugins.generic.authorVersion.publication.submitVersion')},
                });
            }
            if (state.isEditor && state.isLatest && state.isDraft && publication.version > 1) {
                result.push({
                    component: 'AuthorVersionAction',
                    props: {publication, submission, operation: 'deleteVersion', title: label('deleteVersion', 'plugins.generic.authorVersion.deleteVersion'), isWarnable: true},
                });
            }
            return result;
        });
    });
})(pkp);
