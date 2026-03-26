export const resolveNotificationActionUrl = (
    notification,
    {
        isStaff = false,
        isAdmin = false,
        routeFn = route,
        currentUrl = '',
    } = {},
) => {
    const category = String(notification?.category || 'general');
    const event = String(notification?.event || 'created');
    const explicitUrl = String(notification?.action_url || '').trim();
    const resource = notification?.resource || {};
    const notificationIndexUrl = routeFn('notifications.index');

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

    if (category === 'study_group' && resource?.study_group_uuid) {
        return isAdmin
            ? routeFn('groups.detail', { uuid: resource.study_group_uuid })
            : notificationIndexUrl;
    }

    if (category === 'creation' && resource?.id) {
        return routeFn('hall.creations.show', { creation: resource.id });
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
