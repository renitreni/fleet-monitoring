export function publishRoute(form, storeUrl, points, checkpoints, options) {
    form.transform((data) => ({
        ...data,
        route_points: points.map(({ latitude, longitude }) => ({ latitude, longitude })),
        checkpoints,
    }));
    form.post(storeUrl, options);
}
