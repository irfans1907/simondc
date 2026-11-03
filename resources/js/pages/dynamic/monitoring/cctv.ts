export type Camera = {
    id: number;
    name: string;
    location: string;
    status: 'online' | 'offline';
    recording: boolean;
    snapshot: string | null;
};

const placeholder = '/images/cctv-placeholder.svg';

export const cameras: Camera[] = [
    { id: 1, name: 'CAM-01 Main Entrance', location: 'Entrance', status: 'online', recording: true, snapshot: placeholder },
    { id: 2, name: 'CAM-02 Server Room A Door', location: 'Server Room A', status: 'online', recording: true, snapshot: placeholder },
    { id: 3, name: 'CAM-03 Cold Aisle 1', location: 'Cold Aisle 1', status: 'online', recording: false, snapshot: placeholder },
    { id: 4, name: 'CAM-04 Hot Aisle 1', location: 'Hot Aisle 1', status: 'online', recording: false, snapshot: placeholder },
    { id: 5, name: 'CAM-05 UPS Room', location: 'UPS Room', status: 'online', recording: true, snapshot: placeholder },
    { id: 6, name: 'CAM-06 Generator Room', location: 'Generator Room', status: 'online', recording: false, snapshot: placeholder },
    { id: 7, name: 'CAM-07 CRAC Area', location: 'CRAC Area', status: 'offline', recording: false, snapshot: placeholder },
    { id: 8, name: 'CAM-08 Rack Aisle B', location: 'Rack Aisle B', status: 'online', recording: true, snapshot: placeholder },
    { id: 9, name: 'CAM-09 Rear Entrance', location: 'Rear Entrance', status: 'online', recording: true, snapshot: placeholder },
    { id: 10, name: 'CAM-10 Control Desk', location: 'Control Desk', status: 'online', recording: false, snapshot: placeholder },
    { id: 11, name: 'CAM-11 Loading Dock', location: 'Loading Dock', status: 'online', recording: true, snapshot: placeholder },
    { id: 12, name: 'CAM-12 Server Room B', location: 'Server Room B', status: 'online', recording: false, snapshot: placeholder },
];

export const onlineCameras = cameras.filter((camera) => camera.status === 'online').length;

export const recordingCameras = cameras.filter((camera) => camera.recording).length;
