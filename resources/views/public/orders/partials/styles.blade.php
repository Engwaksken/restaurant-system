<style>
    body {
        background: #f5f6f8;
    }

    .brand-bar {
        background: #212529;
        color: #fff;
    }

    .brand-link {
        display: flex;
        align-items: center;
        gap: .6rem;
        color: #fff;
        font-weight: 700;
        text-decoration: none;
        font-size: 1.1rem;
    }

    .order-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .06);
    }

    .success-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e8f5ee;
        color: #198754;
        font-size: 2.4rem;
    }

    .order-timeline {
        display: flex;
        justify-content: space-between;
        position: relative;
        gap: .5rem;
        padding: 0;
    }

    .order-timeline::before {
        content: '';
        position: absolute;
        top: 15px;
        left: 10%;
        right: 10%;
        height: 3px;
        background: #dee2e6;
        z-index: 0;
    }

    .timeline-step {
        flex: 1;
        position: relative;
        z-index: 1;
        text-align: center;
        font-size: .8rem;
        color: #6c757d;
    }

    .timeline-dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #dee2e6;
        color: #fff;
        font-size: .75rem;
        margin-bottom: .4rem;
    }

    .timeline-step.done .timeline-dot {
        background: #198754;
    }

    .timeline-step.current .timeline-dot {
        background: #0d6efd;
        box-shadow: 0 0 0 4px rgba(13, 110, 253, .18);
    }

    .timeline-step.done .timeline-label,
    .timeline-step.current .timeline-label {
        color: #212529;
        font-weight: 600;
    }

    @media (max-width: 575.98px) {
        .timeline-label {
            font-size: .68rem;
        }

        .timeline-dot {
            width: 26px;
            height: 26px;
            font-size: .65rem;
        }
    }
</style>
