--
-- PostgreSQL database dump
--

\restrict ZvBr0hNgHQKBGv6CqegdoVori2er2yvEjX3Sp5294lyX4UjAe3BgPGpHzbgI8sn

-- Dumped from database version 18.6
-- Dumped by pg_dump version 18.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: allocations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.allocations (
    allocation_id integer NOT NULL,
    student_id integer NOT NULL,
    room_id integer NOT NULL,
    application_id integer,
    allocation_date date DEFAULT CURRENT_DATE,
    start_date date,
    end_date date,
    status character varying(30) DEFAULT 'active'::character varying,
    CONSTRAINT allocations_check CHECK (((end_date IS NULL) OR (start_date IS NULL) OR (end_date >= start_date))),
    CONSTRAINT allocations_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'completed'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: allocations_allocation_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.allocations_allocation_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: allocations_allocation_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.allocations_allocation_id_seq OWNED BY public.allocations.allocation_id;


--
-- Name: applications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.applications (
    application_id integer NOT NULL,
    student_id integer NOT NULL,
    academic_year character varying(20) NOT NULL,
    semester character varying(30) NOT NULL,
    preferred_hall_id integer,
    status character varying(30) DEFAULT 'pending'::character varying,
    application_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT applications_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying, 'allocated'::character varying])::text[])))
);


--
-- Name: applications_application_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.applications_application_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: applications_application_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.applications_application_id_seq OWNED BY public.applications.application_id;


--
-- Name: damage_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.damage_records (
    damage_id integer NOT NULL,
    room_id integer NOT NULL,
    caretaker_id integer NOT NULL,
    description text NOT NULL,
    severity character varying(20) NOT NULL,
    status character varying(20) DEFAULT 'Pending'::character varying NOT NULL,
    reported_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    resolved_at timestamp without time zone,
    CONSTRAINT damage_records_severity_check CHECK (((severity)::text = ANY ((ARRAY['Low'::character varying, 'Medium'::character varying, 'High'::character varying])::text[]))),
    CONSTRAINT damage_records_status_check CHECK (((status)::text = ANY ((ARRAY['Pending'::character varying, 'In Progress'::character varying, 'Resolved'::character varying])::text[])))
);


--
-- Name: damage_records_damage_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.damage_records_damage_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: damage_records_damage_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.damage_records_damage_id_seq OWNED BY public.damage_records.damage_id;


--
-- Name: halls; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.halls (
    hall_id integer NOT NULL,
    hall_name character varying(100) NOT NULL,
    description text
);


--
-- Name: halls_hall_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.halls_hall_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: halls_hall_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.halls_hall_id_seq OWNED BY public.halls.hall_id;


--
-- Name: room_inspections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.room_inspections (
    inspection_id integer NOT NULL,
    room_id integer NOT NULL,
    caretaker_id integer NOT NULL,
    condition_status character varying(30) NOT NULL,
    notes text,
    inspection_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT room_inspections_condition_status_check CHECK (((condition_status)::text = ANY ((ARRAY['Good'::character varying, 'Needs Repair'::character varying, 'Damaged'::character varying])::text[])))
);


--
-- Name: room_inspections_inspection_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.room_inspections_inspection_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: room_inspections_inspection_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.room_inspections_inspection_id_seq OWNED BY public.room_inspections.inspection_id;


--
-- Name: rooms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rooms (
    room_id integer NOT NULL,
    hall_id integer NOT NULL,
    room_number character varying(20) NOT NULL,
    room_type character varying(30) NOT NULL,
    capacity integer NOT NULL,
    floor_number integer DEFAULT 0,
    status character varying(30) DEFAULT 'available'::character varying,
    CONSTRAINT rooms_capacity_check CHECK ((capacity > 0)),
    CONSTRAINT rooms_room_type_check CHECK (((room_type)::text = ANY ((ARRAY['Single'::character varying, 'Double'::character varying, 'Triple'::character varying])::text[]))),
    CONSTRAINT rooms_status_check CHECK (((status)::text = ANY ((ARRAY['available'::character varying, 'maintenance'::character varying])::text[])))
);


--
-- Name: rooms_room_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rooms_room_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rooms_room_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rooms_room_id_seq OWNED BY public.rooms.room_id;


--
-- Name: transfer_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transfer_requests (
    transfer_id integer NOT NULL,
    student_id integer NOT NULL,
    current_allocation_id integer NOT NULL,
    requested_hall_id integer NOT NULL,
    reason text NOT NULL,
    status character varying(30) DEFAULT 'pending'::character varying,
    request_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    reviewed_at timestamp without time zone,
    reviewed_by integer,
    CONSTRAINT transfer_requests_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[])))
);


--
-- Name: transfer_requests_transfer_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.transfer_requests_transfer_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transfer_requests_transfer_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.transfer_requests_transfer_id_seq OWNED BY public.transfer_requests.transfer_id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    user_id integer NOT NULL,
    full_name character varying(120) NOT NULL,
    email character varying(150) NOT NULL,
    password_hash character varying(255) NOT NULL,
    role character varying(30) NOT NULL,
    phone character varying(20),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    profile_photo character varying(255),
    CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['student'::character varying, 'officer'::character varying, 'caretaker'::character varying])::text[])))
);


--
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_user_id_seq OWNED BY public.users.user_id;


--
-- Name: vacation_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vacation_requests (
    vacation_id integer NOT NULL,
    student_id integer NOT NULL,
    allocation_id integer NOT NULL,
    start_date date NOT NULL,
    return_date date NOT NULL,
    reason text NOT NULL,
    status character varying(30) DEFAULT 'pending'::character varying,
    request_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    reviewed_at timestamp without time zone,
    reviewed_by integer,
    CONSTRAINT vacation_requests_check CHECK ((return_date > start_date)),
    CONSTRAINT vacation_requests_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[])))
);


--
-- Name: vacation_requests_vacation_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vacation_requests_vacation_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vacation_requests_vacation_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vacation_requests_vacation_id_seq OWNED BY public.vacation_requests.vacation_id;


--
-- Name: allocations allocation_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations ALTER COLUMN allocation_id SET DEFAULT nextval('public.allocations_allocation_id_seq'::regclass);


--
-- Name: applications application_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.applications ALTER COLUMN application_id SET DEFAULT nextval('public.applications_application_id_seq'::regclass);


--
-- Name: damage_records damage_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.damage_records ALTER COLUMN damage_id SET DEFAULT nextval('public.damage_records_damage_id_seq'::regclass);


--
-- Name: halls hall_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.halls ALTER COLUMN hall_id SET DEFAULT nextval('public.halls_hall_id_seq'::regclass);


--
-- Name: room_inspections inspection_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.room_inspections ALTER COLUMN inspection_id SET DEFAULT nextval('public.room_inspections_inspection_id_seq'::regclass);


--
-- Name: rooms room_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rooms ALTER COLUMN room_id SET DEFAULT nextval('public.rooms_room_id_seq'::regclass);


--
-- Name: transfer_requests transfer_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfer_requests ALTER COLUMN transfer_id SET DEFAULT nextval('public.transfer_requests_transfer_id_seq'::regclass);


--
-- Name: users user_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN user_id SET DEFAULT nextval('public.users_user_id_seq'::regclass);


--
-- Name: vacation_requests vacation_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacation_requests ALTER COLUMN vacation_id SET DEFAULT nextval('public.vacation_requests_vacation_id_seq'::regclass);


--
-- Name: allocations allocations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_pkey PRIMARY KEY (allocation_id);


--
-- Name: applications applications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.applications
    ADD CONSTRAINT applications_pkey PRIMARY KEY (application_id);


--
-- Name: damage_records damage_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.damage_records
    ADD CONSTRAINT damage_records_pkey PRIMARY KEY (damage_id);


--
-- Name: halls halls_hall_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.halls
    ADD CONSTRAINT halls_hall_name_key UNIQUE (hall_name);


--
-- Name: halls halls_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.halls
    ADD CONSTRAINT halls_pkey PRIMARY KEY (hall_id);


--
-- Name: room_inspections room_inspections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.room_inspections
    ADD CONSTRAINT room_inspections_pkey PRIMARY KEY (inspection_id);


--
-- Name: rooms rooms_hall_id_room_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rooms
    ADD CONSTRAINT rooms_hall_id_room_number_key UNIQUE (hall_id, room_number);


--
-- Name: rooms rooms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rooms
    ADD CONSTRAINT rooms_pkey PRIMARY KEY (room_id);


--
-- Name: transfer_requests transfer_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfer_requests
    ADD CONSTRAINT transfer_requests_pkey PRIMARY KEY (transfer_id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: vacation_requests vacation_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacation_requests
    ADD CONSTRAINT vacation_requests_pkey PRIMARY KEY (vacation_id);


--
-- Name: idx_allocations_room; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_allocations_room ON public.allocations USING btree (room_id);


--
-- Name: idx_applications_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_applications_status ON public.applications USING btree (status);


--
-- Name: idx_applications_student; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_applications_student ON public.applications USING btree (student_id);


--
-- Name: idx_damage_records_room; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_damage_records_room ON public.damage_records USING btree (room_id);


--
-- Name: idx_damage_records_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_damage_records_status ON public.damage_records USING btree (status);


--
-- Name: idx_room_inspections_room; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_room_inspections_room ON public.room_inspections USING btree (room_id);


--
-- Name: idx_transfer_requests_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_transfer_requests_status ON public.transfer_requests USING btree (status);


--
-- Name: idx_transfer_requests_student; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_transfer_requests_student ON public.transfer_requests USING btree (student_id);


--
-- Name: idx_vacation_requests_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_vacation_requests_status ON public.vacation_requests USING btree (status);


--
-- Name: idx_vacation_requests_student; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_vacation_requests_student ON public.vacation_requests USING btree (student_id);


--
-- Name: one_active_allocation_per_student; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX one_active_allocation_per_student ON public.allocations USING btree (student_id) WHERE ((status)::text = 'active'::text);


--
-- Name: allocations allocations_application_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_application_id_fkey FOREIGN KEY (application_id) REFERENCES public.applications(application_id);


--
-- Name: allocations allocations_room_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_room_id_fkey FOREIGN KEY (room_id) REFERENCES public.rooms(room_id);


--
-- Name: allocations allocations_student_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_student_id_fkey FOREIGN KEY (student_id) REFERENCES public.users(user_id);


--
-- Name: applications applications_preferred_hall_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.applications
    ADD CONSTRAINT applications_preferred_hall_id_fkey FOREIGN KEY (preferred_hall_id) REFERENCES public.halls(hall_id);


--
-- Name: applications applications_student_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.applications
    ADD CONSTRAINT applications_student_id_fkey FOREIGN KEY (student_id) REFERENCES public.users(user_id);


--
-- Name: damage_records damage_records_caretaker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.damage_records
    ADD CONSTRAINT damage_records_caretaker_id_fkey FOREIGN KEY (caretaker_id) REFERENCES public.users(user_id);


--
-- Name: damage_records damage_records_room_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.damage_records
    ADD CONSTRAINT damage_records_room_id_fkey FOREIGN KEY (room_id) REFERENCES public.rooms(room_id);


--
-- Name: room_inspections room_inspections_caretaker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.room_inspections
    ADD CONSTRAINT room_inspections_caretaker_id_fkey FOREIGN KEY (caretaker_id) REFERENCES public.users(user_id);


--
-- Name: room_inspections room_inspections_room_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.room_inspections
    ADD CONSTRAINT room_inspections_room_id_fkey FOREIGN KEY (room_id) REFERENCES public.rooms(room_id);


--
-- Name: rooms rooms_hall_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rooms
    ADD CONSTRAINT rooms_hall_id_fkey FOREIGN KEY (hall_id) REFERENCES public.halls(hall_id);


--
-- Name: transfer_requests transfer_requests_current_allocation_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfer_requests
    ADD CONSTRAINT transfer_requests_current_allocation_id_fkey FOREIGN KEY (current_allocation_id) REFERENCES public.allocations(allocation_id);


--
-- Name: transfer_requests transfer_requests_requested_hall_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfer_requests
    ADD CONSTRAINT transfer_requests_requested_hall_id_fkey FOREIGN KEY (requested_hall_id) REFERENCES public.halls(hall_id);


--
-- Name: transfer_requests transfer_requests_reviewed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfer_requests
    ADD CONSTRAINT transfer_requests_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES public.users(user_id);


--
-- Name: transfer_requests transfer_requests_student_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfer_requests
    ADD CONSTRAINT transfer_requests_student_id_fkey FOREIGN KEY (student_id) REFERENCES public.users(user_id);


--
-- Name: vacation_requests vacation_requests_allocation_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacation_requests
    ADD CONSTRAINT vacation_requests_allocation_id_fkey FOREIGN KEY (allocation_id) REFERENCES public.allocations(allocation_id);


--
-- Name: vacation_requests vacation_requests_reviewed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacation_requests
    ADD CONSTRAINT vacation_requests_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES public.users(user_id);


--
-- Name: vacation_requests vacation_requests_student_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacation_requests
    ADD CONSTRAINT vacation_requests_student_id_fkey FOREIGN KEY (student_id) REFERENCES public.users(user_id);


--
-- PostgreSQL database dump complete
--

\unrestrict ZvBr0hNgHQKBGv6CqegdoVori2er2yvEjX3Sp5294lyX4UjAe3BgPGpHzbgI8sn

