export const resolveNotificationActionUrl = (
    notification,
    {
        isStaff = false,
        isAdmin = false,
        preferRedesign = false,
        routeFn = route,
        currentUrl = '',
    } = {},
) => {
    const category = String(notification?.category || 'general');
    const event = String(notification?.event || 'created');
    const explicitUrl = String(notification?.action_url || '').trim();
    const resource = notification?.resource || {};
    const meta = notification?.meta || {};
    const notificationIndexUrl = routeFn('notifications.index');

    if (preferRedesign && isStaff) {
        const redesignIndex = routeFn('redesign.notifications');
        if (category === 'assignment' && event === 'submitted' && resource?.uuid) {
            return routeFn('redesign.submissions.inspect', { submission: resource.uuid });
        }
        if (category === 'study_group' && resource?.study_group_uuid) {
            return isAdmin
                ? routeFn('redesign.study-groups.membership', { uuid: resource.study_group_uuid })
                : routeFn('redesign.study-groups');
        }
        if (category === 'event' && resource?.uuid) {
            return routeFn('redesign.events.show', { event: resource.uuid });
        }
        if (category === 'creation' && event === 'review_assigned' && resource?.id) {
            return routeFn('redesign.creations.review', { creation: resource.id });
        }
        if (category === 'announcement') {
            return routeFn(isAdmin ? 'redesign.admin-dashboard' : 'redesign.class-dashboard');
        }
        return redesignIndex;
    }

    if (category === 'grade' && resource?.uuid) {
        return routeFn('submissions.show', { submission: resource.uuid });
    }

    if (category === 'assignment' && event === 'submitted' && resource?.uuid) {
        return isStaff
            ? routeFn('admin.submissions.inspect', { submission: resource.uuid })
            : notificationIndexUrl;
    }

    if (category === 'assignment' && resource?.type === 'quest' && resource?.uuid) {
        return routeFn('quests.show', { quest: resource.uuid });
    }

    if (category === 'event' && resource?.uuid) {
        return routeFn('events.show', { event: resource.uuid });
    }

    if (category === 'daily_quest') {
        return `${routeFn('lobby')}#daily-quests`;
    }

    if (category === 'study_group' && event === 'join_rejected') {
        return routeFn('groups.index');
    }

    if (category === 'study_group' && resource?.study_group_uuid) {
        return isAdmin
            ? routeFn('groups.detail', { uuid: resource.study_group_uuid })
            : notificationIndexUrl;
    }

    if (category === 'creation') {
        const resourceType = String(resource?.type || '').toLowerCase();
        const resolvedCreationSlug = String(
            resource?.slug
            || resource?.creation_slug
            || meta?.creation_slug
            || '',
        ).trim();
        const resolvedCreationId = Number(
            resource?.creation_id
            || (resourceType === 'creation' ? resource?.id : 0)
            || meta?.creation_id
            || 0,
        );

        if (resolvedCreationSlug !== '') {
            return routeFn('hall.creations.show', { creation: resolvedCreationSlug });
        }

        if (resolvedCreationId > 0) {
            return routeFn('hall.creations.show', { creation: resolvedCreationId });
        }
    }

    if (explicitUrl && explicitUrl !== notificationIndexUrl && explicitUrl !== currentUrl) {
        return explicitUrl;
    }

    if (category === 'chat') {
        return routeFn('lobby');
    }

    if (category === 'announcement') {
        return isStaff ? routeFn('dashboard') : routeFn('lobby');
    }

    return explicitUrl || notificationIndexUrl;
};
