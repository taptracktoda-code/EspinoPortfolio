import { bucket, defineRailway, mysql, project, service } from 'railway/iac';

export default defineRailway(ctx => {
    const database = mysql('MySQL');
    const uploads = bucket('PortfolioBucket', { region: 'sin' });
    const region = { 'asia-southeast1-eqsg3a': 1 };
    const runtime = {
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        APP_KEY: ctx.shared.APP_KEY,
        APP_URL: ctx.shared.APP_URL,
        LOG_CHANNEL: 'stderr',
        DB_CONNECTION: 'mysql',
        DB_HOST: database.env.MYSQLHOST,
        DB_PORT: database.env.MYSQLPORT,
        DB_DATABASE: database.env.MYSQLDATABASE,
        DB_USERNAME: database.env.MYSQLUSER,
        DB_PASSWORD: database.env.MYSQLPASSWORD,
        SESSION_DRIVER: 'database',
        SESSION_ENCRYPT: 'true',
        SESSION_SECURE_COOKIE: 'true',
        CACHE_STORE: 'database',
        QUEUE_CONNECTION: 'database',
        MAIL_MAILER: 'resend',
        RESEND_API_KEY: ctx.shared.RESEND_API_KEY,
        MAIL_FROM_ADDRESS: ctx.shared.MAIL_FROM_ADDRESS,
        MAIL_FROM_NAME: 'Personal Portfolio',
        MAIL_REPLY_TO_ADDRESS: ctx.shared.MAIL_REPLY_TO_ADDRESS,
    };

    const web = service('Portfolio Web', {
        preDeploy: 'php artisan migrate --force',
        healthcheck: '/up',
        healthcheckTimeout: 120,
        replicas: region,
        env: {
            ...runtime,
            FILESYSTEM_DISK: 's3',
            AWS_ACCESS_KEY_ID: '${{PortfolioBucket.ACCESS_KEY_ID}}',
            AWS_SECRET_ACCESS_KEY: '${{PortfolioBucket.SECRET_ACCESS_KEY}}',
            AWS_DEFAULT_REGION: '${{PortfolioBucket.REGION}}',
            AWS_BUCKET: '${{PortfolioBucket.BUCKET}}',
            AWS_ENDPOINT: '${{PortfolioBucket.ENDPOINT}}',
            AWS_USE_PATH_STYLE_ENDPOINT: 'false',
        },
    });

    const worker = service('Portfolio Worker', {
        start: 'php artisan queue:work database --queue=default --sleep=3 --timeout=45',
        replicas: region,
        env: runtime,
    });

    return project(ctx.projectName, { resources: [database, uploads, web, worker] });
});
